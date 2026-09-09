<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\PaymentReconciliation;
use App\Models\User;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Payment Reconciliation Service
 * 
 * Handles automatic and manual matching of payments to invoices.
 */
class PaymentReconciliationService
{
    protected InvoiceService $invoiceService;

    public function __construct(InvoiceService $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }

    /**
     * Attempt to auto-match a payment to an invoice
     * Returns matched invoice or null
     */
    public function autoMatchPayment(Payment $payment): ?Invoice
    {
        // Skip if payment already matched
        $existingReconciliation = PaymentReconciliation::where('payment_id', $payment->id)
            ->whereNotNull('invoice_id')
            ->first();

        if ($existingReconciliation) {
            return $existingReconciliation->invoice;
        }

        $user = User::find($payment->user_id);
        if (!$user) {
            return null;
        }

        // Try matching rules in priority order
        $match = null;
        $confidence = null;

        // Rule 1: Reference Number Match (HIGH confidence)
        if ($payment->metadata && isset($payment->metadata['AccountReference'])) {
            $reference = $payment->metadata['AccountReference'];
            $match = Invoice::where('user_id', $user->id)
                ->where('reference', $reference)
                ->where('balance', '>', 0)
                ->first();
            
            if ($match) {
                $confidence = 'high';
            }
        }

        // Rule 2: Amount Match (MEDIUM confidence)
        if (!$match) {
            $match = Invoice::where('user_id', $user->id)
                ->where('balance', $payment->amount)
                ->where('balance', '>', 0)
                ->where('due_date', '>=', Carbon::parse($payment->transaction_time)->subDays(7))
                ->orderBy('due_date', 'desc')
                ->first();
            
            if ($match) {
                $confidence = 'medium';
            }
        }

        // Rule 3: Phone Number Match (MEDIUM confidence)
        if (!$match && $payment->phone_number) {
            // Find customer by phone
            $customer = Customer::where('user_id', $user->id)
                ->where('phone', $payment->phone_number)
                ->first();

            if ($customer) {
                $match = Invoice::where('user_id', $user->id)
                    ->where('customer_id', $customer->id)
                    ->where('balance', '>', 0)
                    ->where('balance', '>=', $payment->amount)
                    ->orderBy('due_date', 'asc')
                    ->first();
                
                if ($match) {
                    $confidence = 'medium';
                }
            }
        }

        // Rule 4: Date Proximity Match (LOW confidence)
        if (!$match) {
            $paymentDate = Carbon::parse($payment->transaction_time);
            $match = Invoice::where('user_id', $user->id)
                ->where('balance', '>', 0)
                ->where('balance', '>=', $payment->amount)
                ->whereBetween('due_date', [
                    $paymentDate->copy()->subDays(7),
                    $paymentDate->copy()->addDays(7)
                ])
                ->orderBy('due_date', 'asc')
                ->first();
            
            if ($match) {
                $confidence = 'low';
            }
        }

        // If match found, create reconciliation record
        if ($match) {
            return $this->matchPaymentToInvoice($payment, $match, $payment->amount, 'auto', $confidence);
        }

        // Create unmatched reconciliation record
        PaymentReconciliation::create([
            'user_id' => $user->id,
            'payment_id' => $payment->id,
            'invoice_id' => null,
            'match_type' => 'auto',
            'match_confidence' => null,
            'amount_matched' => 0,
        ]);

        Log::info('Payment auto-match attempted - no match found', [
            'payment_id' => $payment->id,
            'transaction_id' => $payment->transaction_id,
            'amount' => $payment->amount,
        ]);

        return null;
    }

    /**
     * Manually match payment to invoice
     */
    public function matchPaymentToInvoice(
        Payment $payment,
        Invoice $invoice,
        float $amount,
        string $matchType = 'manual',
        ?string $confidence = null
    ): Invoice {
        return DB::transaction(function () use ($payment, $invoice, $amount, $matchType, $confidence) {
            // Validate match
            if ($invoice->user_id !== $payment->user_id) {
                throw new \Exception('Payment and invoice must belong to the same user.');
            }

            if ($amount > $invoice->balance) {
                // Allow overpayment (create credit)
                $amount = $invoice->balance;
            }

            // Create or update reconciliation record
            $reconciliation = PaymentReconciliation::updateOrCreate(
                [
                    'payment_id' => $payment->id,
                ],
                [
                    'user_id' => $payment->user_id,
                    'invoice_id' => $invoice->id,
                    'match_type' => $matchType,
                    'match_confidence' => $confidence,
                    'matched_by' => $matchType === 'manual' ? auth()->id() : null,
                    'matched_at' => Carbon::now(),
                    'amount_matched' => $amount,
                ]
            );

            // Create invoice payment link
            InvoicePayment::updateOrCreate(
                [
                    'invoice_id' => $invoice->id,
                    'payment_id' => $payment->id,
                ],
                [
                    'amount' => $amount,
                    'payment_date' => Carbon::parse($payment->transaction_time)->toDateString(),
                ]
            );

            // Update invoice payment status
            $this->invoiceService->markAsPaid($invoice, $amount);

            Log::info('Payment matched to invoice', [
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'amount' => $amount,
                'match_type' => $matchType,
                'confidence' => $confidence,
            ]);

            return $invoice->fresh();
        });
    }

    /**
     * Unmatch payment from invoice
     */
    public function unmatchPayment(Payment $payment): bool
    {
        return DB::transaction(function () use ($payment) {
            $reconciliation = PaymentReconciliation::where('payment_id', $payment->id)
                ->whereNotNull('invoice_id')
                ->first();

            if (!$reconciliation) {
                return false;
            }

            $invoice = $reconciliation->invoice;
            $amount = $reconciliation->amount_matched;

            // Remove invoice payment link
            InvoicePayment::where('invoice_id', $invoice->id)
                ->where('payment_id', $payment->id)
                ->delete();

            // Recalculate invoice balance
            $invoice->paid_amount = max(0, $invoice->paid_amount - $amount);
            $invoice->balance = $invoice->total_amount - $invoice->paid_amount;
            $invoice->updateStatus();
            $invoice->save();

            // Update reconciliation record
            $reconciliation->update([
                'invoice_id' => null,
                'matched_at' => null,
                'matched_by' => null,
                'amount_matched' => 0,
            ]);

            Log::info('Payment unmatched from invoice', [
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
            ]);

            return true;
        });
    }

    /**
     * Get unmatched payments for a user
     */
    public function getUnmatchedPayments(User $user, array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = Payment::where('user_id', $user->id)
            ->whereDoesntHave('invoicePayments')
            ->orderBy('transaction_time', 'desc');

        // Apply filters
        if (isset($filters['date_from'])) {
            $query->where('transaction_time', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('transaction_time', '<=', $filters['date_to']);
        }

        if (isset($filters['min_amount'])) {
            $query->where('amount', '>=', $filters['min_amount']);
        }

        if (isset($filters['max_amount'])) {
            $query->where('amount', '<=', $filters['max_amount']);
        }

        return $query->get();
    }

    /**
     * Get unpaid invoices for a user
     */
    public function getUnpaidInvoices(User $user, array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = Invoice::where('user_id', $user->id)
            ->unpaid()
            ->with(['customer', 'items'])
            ->orderBy('due_date', 'asc');

        // Apply filters
        if (isset($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (isset($filters['date_from'])) {
            $query->where('due_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('due_date', '<=', $filters['date_to']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->get();
    }

    /**
     * Generate reconciliation report
     */
    public function generateReconciliationReport(
        User $user,
        Carbon $startDate,
        Carbon $endDate
    ): array {
        $matchedPayments = PaymentReconciliation::where('user_id', $user->id)
            ->whereNotNull('invoice_id')
            ->whereBetween('matched_at', [$startDate, $endDate])
            ->with(['payment', 'invoice'])
            ->get();

        $unmatchedPayments = $this->getUnmatchedPayments($user, [
            'date_from' => $startDate,
            'date_to' => $endDate,
        ]);

        $unpaidInvoices = $this->getUnpaidInvoices($user, [
            'date_from' => $startDate,
            'date_to' => $endDate,
        ]);

        return [
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'matched' => [
                'count' => $matchedPayments->count(),
                'total_amount' => $matchedPayments->sum('amount_matched'),
                'payments' => $matchedPayments,
            ],
            'unmatched' => [
                'count' => $unmatchedPayments->count(),
                'total_amount' => $unmatchedPayments->sum('amount'),
                'payments' => $unmatchedPayments,
            ],
            'unpaid_invoices' => [
                'count' => $unpaidInvoices->count(),
                'total_amount' => $unpaidInvoices->sum('balance'),
                'invoices' => $unpaidInvoices,
            ],
        ];
    }

    /**
     * Suggest matches for an unmatched payment
     */
    public function suggestMatches(Payment $payment, int $limit = 5): \Illuminate\Database\Eloquent\Collection
    {
        $user = User::find($payment->user_id);
        if (!$user) {
            return collect();
        }

        // Find invoices with similar amount or reference
        $suggestions = Invoice::where('user_id', $user->id)
            ->where('balance', '>', 0)
            ->where(function ($query) use ($payment) {
                // Amount match (within 10%)
                $query->whereBetween('balance', [
                    $payment->amount * 0.9,
                    $payment->amount * 1.1
                ]);

                // Reference match
                if ($payment->metadata && isset($payment->metadata['AccountReference'])) {
                    $query->orWhere('reference', $payment->metadata['AccountReference']);
                }

                // Customer phone match
                if ($payment->phone_number) {
                    $query->orWhereHas('customer', function ($q) use ($payment) {
                        $q->where('phone', $payment->phone_number);
                    });
                }
            })
            ->with(['customer'])
            ->orderByRaw('ABS(balance - ?) ASC', [$payment->amount])
            ->limit($limit)
            ->get();

        return $suggestions;
    }
}
