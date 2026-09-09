<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Invoice Service
 * 
 * Handles invoice creation, management, PDF generation, and sending.
 */
class InvoiceService
{
    /**
     * Create a new invoice
     */
    public function create(array $data, User $user): Invoice
    {
        return DB::transaction(function () use ($data, $user) {
            // Generate invoice number
            $invoiceNumber = $this->generateInvoiceNumber($user, $data['issue_date'] ?? Carbon::today());
            
            // Create invoice
            $invoice = Invoice::create([
                'user_id' => $user->id,
                'customer_id' => $data['customer_id'] ?? null,
                'invoice_number' => $invoiceNumber,
                'status' => $data['status'] ?? 'draft',
                'issue_date' => $data['issue_date'] ?? Carbon::today(),
                'due_date' => $data['due_date'] ?? Carbon::today()->addDays(30),
                'subtotal' => 0,
                'tax_rate' => $data['tax_rate'] ?? 0,
                'tax_amount' => 0,
                'discount_amount' => $data['discount_amount'] ?? 0,
                'total_amount' => 0,
                'paid_amount' => 0,
                'balance' => 0,
                'currency' => $data['currency'] ?? 'KES',
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
                'reference' => $data['reference'] ?? $invoiceNumber,
                'recurring_id' => $data['recurring_id'] ?? null,
            ]);

            // Create invoice items
            if (isset($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $index => $item) {
                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'description' => $item['description'],
                        'quantity' => $item['quantity'] ?? 1,
                        'unit_price' => $item['unit_price'],
                        'tax_rate' => $item['tax_rate'] ?? $invoice->tax_rate,
                        'discount_amount' => $item['discount_amount'] ?? 0,
                        'sort_order' => $index,
                    ]);
                }
            }

            // Calculate totals
            $this->calculateTotals($invoice);
            $invoice->refresh();

            Log::info('Invoice created', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'user_id' => $user->id,
            ]);

            return $invoice;
        });
    }

    /**
     * Update an existing invoice
     */
    public function update(Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data) {
            // Update invoice fields
            $invoice->fill([
                'customer_id' => $data['customer_id'] ?? $invoice->customer_id,
                'status' => $data['status'] ?? $invoice->status,
                'issue_date' => $data['issue_date'] ?? $invoice->issue_date,
                'due_date' => $data['due_date'] ?? $invoice->due_date,
                'tax_rate' => $data['tax_rate'] ?? $invoice->tax_rate,
                'discount_amount' => $data['discount_amount'] ?? $invoice->discount_amount,
                'currency' => $data['currency'] ?? $invoice->currency,
                'notes' => $data['notes'] ?? $invoice->notes,
                'terms' => $data['terms'] ?? $invoice->terms,
                'reference' => $data['reference'] ?? $invoice->reference,
            ]);
            $invoice->save();

            // Update or create invoice items
            if (isset($data['items']) && is_array($data['items'])) {
                // Delete existing items
                $invoice->items()->delete();

                // Create new items
                foreach ($data['items'] as $index => $item) {
                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'description' => $item['description'],
                        'quantity' => $item['quantity'] ?? 1,
                        'unit_price' => $item['unit_price'],
                        'tax_rate' => $item['tax_rate'] ?? $invoice->tax_rate,
                        'discount_amount' => $item['discount_amount'] ?? 0,
                        'sort_order' => $index,
                    ]);
                }
            }

            // Recalculate totals
            $this->calculateTotals($invoice);
            $invoice->refresh();

            Log::info('Invoice updated', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
            ]);

            return $invoice;
        });
    }

    /**
     * Delete an invoice
     */
    public function delete(Invoice $invoice): bool
    {
        if ($invoice->status === 'paid') {
            throw new \Exception('Cannot delete a paid invoice.');
        }

        return DB::transaction(function () use ($invoice) {
            $invoiceNumber = $invoice->invoice_number;
            $invoiceId = $invoice->id;

            // Delete items
            $invoice->items()->delete();

            // Delete invoice payments
            $invoice->invoicePayments()->delete();

            // Delete invoice
            $invoice->delete();

            Log::info('Invoice deleted', [
                'invoice_id' => $invoiceId,
                'invoice_number' => $invoiceNumber,
            ]);

            return true;
        });
    }

    /**
     * Generate unique invoice number
     * Format: INV-{YEAR}-{SEQUENCE}
     */
    public function generateInvoiceNumber(User $user, $date = null): string
    {
        $date = $date ? Carbon::parse($date) : Carbon::today();
        $year = $date->format('Y');
        $prefix = "INV-{$year}-";

        // Get the last invoice number for this user and year
        $lastInvoice = Invoice::where('user_id', $user->id)
            ->where('invoice_number', 'like', $prefix . '%')
            ->orderBy('invoice_number', 'desc')
            ->first();

        if ($lastInvoice) {
            // Extract sequence number
            $lastSequence = (int) Str::after($lastInvoice->invoice_number, $prefix);
            $sequence = $lastSequence + 1;
        } else {
            $sequence = 1;
        }

        return $prefix . str_pad($sequence, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Calculate invoice totals
     */
    public function calculateTotals(Invoice $invoice): array
    {
        // Calculate subtotal from items
        $subtotal = $invoice->items()->sum(DB::raw('(quantity * unit_price) - discount_amount'));

        // Calculate tax
        $taxAmount = ($subtotal - $invoice->discount_amount) * ($invoice->tax_rate / 100);

        // Calculate total
        $totalAmount = $subtotal - $invoice->discount_amount + $taxAmount;

        // Calculate balance
        $balance = $totalAmount - $invoice->paid_amount;

        // Update invoice
        $invoice->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'balance' => $balance,
        ]);

        // Update status if needed
        $invoice->updateStatus();

        return [
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'paid_amount' => $invoice->paid_amount,
            'balance' => $balance,
        ];
    }

    /**
     * Mark invoice as paid (or partially paid)
     */
    public function markAsPaid(Invoice $invoice, float $amount): Invoice
    {
        $newPaidAmount = $invoice->paid_amount + $amount;
        
        // Don't allow overpayment (or allow it as credit)
        if ($newPaidAmount > $invoice->total_amount) {
            $newPaidAmount = $invoice->total_amount;
        }

        $invoice->update([
            'paid_amount' => $newPaidAmount,
            'balance' => $invoice->total_amount - $newPaidAmount,
        ]);

        $invoice->updateStatus();

        if ($invoice->balance <= 0 && !$invoice->paid_date) {
            $invoice->update(['paid_date' => Carbon::today()]);
        }

        Log::info('Invoice payment recorded', [
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'new_balance' => $invoice->balance,
        ]);

        return $invoice->fresh();
    }

    /**
     * Mark invoice as sent
     */
    public function markAsSent(Invoice $invoice): Invoice
    {
        if ($invoice->status === 'draft') {
            $invoice->update([
                'status' => 'sent',
                'sent_at' => Carbon::now(),
            ]);

            Log::info('Invoice marked as sent', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
            ]);
        }

        return $invoice->fresh();
    }

    /**
     * Mark invoice as viewed
     */
    public function markAsViewed(Invoice $invoice): Invoice
    {
        if ($invoice->status === 'sent' && !$invoice->viewed_at) {
            $invoice->update([
                'status' => 'viewed',
                'viewed_at' => Carbon::now(),
            ]);

            Log::info('Invoice marked as viewed', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
            ]);
        }

        return $invoice->fresh();
    }

    /**
     * Mark invoice as overdue
     */
    public function markAsOverdue(Invoice $invoice): Invoice
    {
        if ($invoice->isOverdue() && $invoice->status !== 'overdue') {
            $invoice->update(['status' => 'overdue']);

            Log::info('Invoice marked as overdue', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'due_date' => $invoice->due_date,
            ]);
        }

        return $invoice->fresh();
    }

    /**
     * Cancel an invoice
     */
    public function cancel(Invoice $invoice, string $reason = null): Invoice
    {
        if ($invoice->status === 'paid') {
            throw new \Exception('Cannot cancel a paid invoice.');
        }

        $invoice->update([
            'status' => 'cancelled',
            'notes' => $invoice->notes . ($reason ? "\n\nCancelled: {$reason}" : ''),
        ]);

        Log::info('Invoice cancelled', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'reason' => $reason,
        ]);

        return $invoice->fresh();
    }

    /**
     * Generate PDF for invoice
     * TODO: Implement PDF generation using DomPDF or similar
     */
    public function generatePdf(Invoice $invoice): string
    {
        // Placeholder - will implement PDF generation
        $pdfPath = storage_path('app/invoices/' . $invoice->invoice_number . '.pdf');
        
        // Ensure directory exists
        $directory = dirname($pdfPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // TODO: Generate PDF using DomPDF
        // For now, just return the path
        $invoice->update(['pdf_path' => $pdfPath]);

        Log::info('Invoice PDF generated', [
            'invoice_id' => $invoice->id,
            'pdf_path' => $pdfPath,
        ]);

        return $pdfPath;
    }

    /**
     * Send invoice via email
     * TODO: Implement email sending
     */
    public function sendEmail(Invoice $invoice): bool
    {
        if (!$invoice->customer || !$invoice->customer->email) {
            throw new \Exception('Customer email not found.');
        }

        // TODO: Implement email sending
        // For now, just mark as sent
        $this->markAsSent($invoice);

        Log::info('Invoice email sent', [
            'invoice_id' => $invoice->id,
            'email' => $invoice->customer->email,
        ]);

        return true;
    }

    /**
     * Send invoice via SMS
     * TODO: Integrate with Talksasa SMS service
     */
    public function sendSms(Invoice $invoice): bool
    {
        if (!$invoice->customer || !$invoice->customer->phone) {
            throw new \Exception('Customer phone not found.');
        }

        // TODO: Integrate with TalksasaSmsService
        // For now, just mark as sent
        $this->markAsSent($invoice);

        Log::info('Invoice SMS sent', [
            'invoice_id' => $invoice->id,
            'phone' => $invoice->customer->phone,
        ]);

        return true;
    }

    /**
     * Get overdue invoices for a user
     */
    public function getOverdueInvoices(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return Invoice::where('user_id', $user->id)
            ->overdue()
            ->with(['customer', 'items'])
            ->get();
    }

    /**
     * Get unpaid invoices for a user
     */
    public function getUnpaidInvoices(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return Invoice::where('user_id', $user->id)
            ->unpaid()
            ->with(['customer', 'items'])
            ->get();
    }
}
