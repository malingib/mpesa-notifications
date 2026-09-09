<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Merchant;
use App\Models\User;
use App\Models\SmsTemplate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * SMS Template Service
 * 
 * Handles template resolution, variable extraction, and safe rendering.
 * Supports template hierarchy and rule evaluation.
 */
class SmsTemplateService
{
    /**
     * Resolve template for payment using hierarchy
     * 
     * Priority:
     * 1. Merchant-specific template
     * 2. User default template
     * 3. Global default template
     * 
     * @param Payment $payment
     * @return SmsTemplate|null
     */
    public function resolveTemplate(Payment $payment): ?SmsTemplate
    {
        $merchant = $payment->merchant;
        $user = $payment->user;

        // 1. Check merchant-specific template
        if ($merchant->sms_template_id) {
            $template = SmsTemplate::find($merchant->sms_template_id);
            if ($template && $template->is_active) {
                return $template;
            }
        }

        // 2. Check user default template
        $userTemplate = SmsTemplate::where('user_id', $user->id)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();

        if ($userTemplate) {
            return $userTemplate;
        }

        // 3. Check global default template
        $globalTemplate = SmsTemplate::whereNull('user_id')
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();

        return $globalTemplate;
    }

    /**
     * Extract variables from payment for template rendering
     * 
     * @param Payment $payment
     * @return array
     */
    public function extractVariables(Payment $payment): array
    {
        $merchant = $payment->merchant;
        $transactionTime = $payment->transaction_time;

        return [
            'amount' => $this->formatAmount($payment->amount, $payment->currency),
            'amount_raw' => (string) $payment->amount,
            'currency' => $payment->currency,
            'receipt' => $payment->receipt_number ?? $payment->transaction_id,
            'transaction_id' => $payment->transaction_id,
            'account_name' => $merchant->account_name ?? $merchant->account_number,
            'account_number' => $merchant->account_number,
            'payer_name' => $payment->payer_name ?? 'Customer',
            'payer_phone' => $this->formatPhone($payment->phone_number),
            'reference' => $payment->reference ?? $payment->transaction_id,
            'date' => $transactionTime->format('d/m/Y'),
            'time' => $transactionTime->format('H:i'),
            'datetime' => $transactionTime->format('d/m/Y H:i'),
            'description' => $payment->description ?? 'Payment received',
        ];
    }

    /**
     * Render template with variables
     * 
     * @param string $template Template string with {variables}
     * @param array $variables Variable values
     * @return string Rendered message
     * @throws \InvalidArgumentException
     */
    public function render(string $template, array $variables): string
    {
        // Validate template syntax
        $this->validateTemplate($template, array_keys($variables));

        // Replace variables safely
        $message = $template;
        foreach ($variables as $key => $value) {
            $placeholder = '{' . $key . '}';
            $message = str_replace($placeholder, $this->sanitizeValue($value), $message);
        }

        // Check for unreplaced variables (warn but don't fail)
        if (preg_match('/\{[^}]+\}/', $message, $matches)) {
            Log::warning('Unreplaced variables in template', [
                'variables' => $matches,
                'template' => $template,
            ]);
        }

        // Trim and validate length
        $message = trim($message);
        
        if (strlen($message) > 160) {
            Log::warning('SMS message exceeds 160 characters', [
                'length' => strlen($message),
                'message' => substr($message, 0, 100) . '...',
            ]);
        }

        return $message;
    }

    /**
     * Validate template syntax
     * 
     * @param string $template
     * @param array $allowedVariables
     * @return void
     * @throws \InvalidArgumentException
     */
    public function validateTemplate(string $template, array $allowedVariables): void
    {
        // Extract all variables from template
        preg_match_all('/\{([^}]+)\}/', $template, $matches);
        $usedVariables = $matches[1] ?? [];

        // Check for unknown variables
        $unknownVariables = array_diff($usedVariables, $allowedVariables);
        if (!empty($unknownVariables)) {
            throw new \InvalidArgumentException(
                'Unknown variables in template: ' . implode(', ', $unknownVariables)
            );
        }

        // Check for nested variables (security)
        foreach ($usedVariables as $var) {
            if (preg_match('/\{/', $var)) {
                throw new \InvalidArgumentException('Nested variables not allowed');
            }
        }

        // Check for code injection attempts
        $dangerousPatterns = [
            '/<\?php/',
            '/<script/',
            '/eval\(/',
            '/exec\(/',
            '/system\(/',
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $template, $matches)) {
                throw new \InvalidArgumentException('Potentially dangerous content detected in template');
            }
        }
    }

    /**
     * Evaluate if SMS should be sent based on rules
     * 
     * @param Payment $payment
     * @return bool
     */
    public function shouldSendSms(Payment $payment): bool
    {
        $user = $payment->user;
        $merchant = $payment->merchant;

        // Rule 1: User SMS must be enabled
        if (!$user->sms_enabled) {
            Log::debug('SMS disabled for user', ['user_id' => $user->id]);
            return false;
        }

        // Rule 2: Merchant SMS must be enabled
        if (!$merchant->sms_enabled) {
            Log::debug('SMS disabled for merchant', [
                'merchant_id' => $merchant->id,
                'user_id' => $user->id,
            ]);
            return false;
        }

        // Rule 3: Payment must be completed
        if ($payment->status !== 'completed') {
            Log::debug('SMS skipped - payment not completed', [
                'payment_id' => $payment->id,
                'status' => $payment->status,
            ]);
            return false;
        }

        // Rule 4: SMS not already sent
        if ($payment->sms_sent) {
            Log::debug('SMS already sent', ['payment_id' => $payment->id]);
            return false;
        }

        return true;
    }

    /**
     * Get rendered message for payment
     * 
     * @param Payment $payment
     * @return string|null Returns null if SMS should not be sent
     */
    public function getMessageForPayment(Payment $payment): ?string
    {
        // Check if SMS should be sent
        if (!$this->shouldSendSms($payment)) {
            return null;
        }

        // Resolve template
        $template = $this->resolveTemplate($payment);
        if (!$template) {
            // Use default template if none found
            $templateMessage = $this->getDefaultTemplate();
        } else {
            $templateMessage = $template->message;
        }

        // Extract variables
        $variables = $this->extractVariables($payment);

        // Render template
        try {
            return $this->render($templateMessage, $variables);
        } catch (\InvalidArgumentException $e) {
            Log::error('Template rendering failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
                'template' => $templateMessage,
            ]);
            
            // Fallback to default template
            return $this->render($this->getDefaultTemplate(), $variables);
        }
    }

    /**
     * Format amount with currency
     * 
     * @param float $amount
     * @param string $currency
     * @return string
     */
    protected function formatAmount(float $amount, string $currency = 'KES'): string
    {
        return $currency . ' ' . number_format($amount, 2, '.', ',');
    }

    /**
     * Format phone number (optional masking)
     * 
     * @param string $phone
     * @param bool $masked
     * @return string
     */
    protected function formatPhone(string $phone, bool $masked = false): string
    {
        if ($masked && strlen($phone) > 7) {
            return substr($phone, 0, 3) . '****' . substr($phone, -3);
        }
        return $phone;
    }

    /**
     * Sanitize variable value for safe output
     * 
     * @param mixed $value
     * @return string
     */
    protected function sanitizeValue($value): string
    {
        if (is_null($value)) {
            return '';
        }

        // Convert to string
        $value = (string) $value;

        // Remove null bytes
        $value = str_replace("\0", '', $value);

        // Trim
        $value = trim($value);

        return $value;
    }

    /**
     * Get default template
     * 
     * @return string
     */
    protected function getDefaultTemplate(): string
    {
        return 'Payment of {amount} received. Receipt: {receipt}. Account: {account_name}. Time: {datetime}. Thank you!';
    }
}
