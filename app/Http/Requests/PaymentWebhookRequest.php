<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Payment Webhook Request
 * 
 * Validates incoming M-Pesa payment notification payloads.
 * Supports both Paybill and Till number formats.
 */
class PaymentWebhookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Webhooks don't require user authentication
        // Security is handled by WebhookSecurityMiddleware
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Required fields for all payment types
            'TransactionID' => 'required|string|max:100',
            'Amount' => 'required|numeric|min:0|max:999999999.99',
            'PhoneNumber' => 'required|string|regex:/^254\d{9}$/',
            'TransactionTime' => 'required|string|regex:/^\d{14}$/',
            
            // Paybill-specific fields (at least one account type must be present)
            'BusinessShortCode' => 'required_without:TillNumber|string|max:20',
            'BillRefNumber' => 'nullable|string|max:255',
            
            // Till-specific fields
            'TillNumber' => 'required_without:BusinessShortCode|string|max:20',
            
            // Optional but common fields
            'ReceiptNumber' => 'nullable|string|max:100',
            'RequestID' => 'nullable|string|max:100',
            'ConversationID' => 'nullable|string|max:100',
            'FirstName' => 'nullable|string|max:100',
            'MiddleName' => 'nullable|string|max:100',
            'LastName' => 'nullable|string|max:100',
            'TransactionDesc' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'TransactionID.required' => 'TransactionID is required',
            'Amount.required' => 'Amount is required',
            'Amount.numeric' => 'Amount must be a valid number',
            'Amount.min' => 'Amount must be positive',
            'PhoneNumber.required' => 'PhoneNumber is required',
            'PhoneNumber.regex' => 'PhoneNumber must be in E.164 format (254XXXXXXXXX)',
            'TransactionTime.required' => 'TransactionTime is required',
            'TransactionTime.regex' => 'TransactionTime must be in format YYYYMMDDHHmmss',
            'BusinessShortCode.required_without' => 'BusinessShortCode or TillNumber is required',
            'TillNumber.required_without' => 'BusinessShortCode or TillNumber is required',
        ];
    }

    /**
     * Prepare the data for validation.
     * Normalizes phone number format before validation.
     */
    protected function prepareForValidation(): void
    {
        // Normalize phone number format
        if ($this->has('PhoneNumber')) {
            $phone = preg_replace('/[^0-9]/', '', $this->input('PhoneNumber'));
            
            // Convert 07XXXXXXXX to 2547XXXXXXXX
            if (strlen($phone) === 9 && substr($phone, 0, 1) === '0') {
                $phone = '254' . substr($phone, 1);
            } elseif (strlen($phone) === 12 && substr($phone, 0, 3) === '254') {
                // Already correct format
            } elseif (strlen($phone) === 9) {
                $phone = '254' . $phone;
            }
            
            $this->merge(['PhoneNumber' => $phone]);
        }
    }
}
