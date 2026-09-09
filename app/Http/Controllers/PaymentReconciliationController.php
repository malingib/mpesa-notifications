<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Invoice;
use App\Services\PaymentReconciliationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class PaymentReconciliationController extends Controller
{
    protected PaymentReconciliationService $reconciliationService;

    public function __construct(PaymentReconciliationService $reconciliationService)
    {
        $this->reconciliationService = $reconciliationService;
    }

    /**
     * Display unmatched payments and unpaid invoices
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // Get filters
        $dateFrom = $request->input('date_from', Carbon::now()->subDays(30)->toDateString());
        $dateTo = $request->input('date_to', Carbon::today()->toDateString());

        // Get unmatched payments
        $unmatchedPayments = $this->reconciliationService->getUnmatchedPayments($user, [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);

        // Get unpaid invoices
        $unpaidInvoices = $this->reconciliationService->getUnpaidInvoices($user, [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);

        // Add suggestions for each unmatched payment
        $unmatchedPayments->each(function ($payment) {
            $payment->suggestions = $this->reconciliationService->suggestMatches($payment, 5);
        });

        return view('reconciliation.index', compact('unmatchedPayments', 'unpaidInvoices', 'dateFrom', 'dateTo'));
    }

    /**
     * Manually match payment to invoice
     */
    public function match(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'payment_id' => 'required|exists:payments,id',
            'invoice_id' => 'required|exists:invoices,id',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $payment = Payment::findOrFail($request->input('payment_id'));
        $invoice = Invoice::findOrFail($request->input('invoice_id'));

        // Verify ownership
        if ($payment->user_id !== $user->id || $invoice->user_id !== $user->id) {
            abort(403);
        }

        try {
            $this->reconciliationService->matchPaymentToInvoice(
                $payment,
                $invoice,
                $request->input('amount'),
                'manual'
            );

            return redirect()->back()
                ->with('success', 'Payment matched to invoice successfully.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to match payment: ' . $e->getMessage());
        }
    }

    /**
     * Unmatch payment from invoice
     */
    public function unmatch(Request $request, Payment $payment)
    {
        $user = Auth::user();

        if ($payment->user_id !== $user->id) {
            abort(403);
        }

        try {
            $this->reconciliationService->unmatchPayment($payment);

            return redirect()->back()
                ->with('success', 'Payment unmatched successfully.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to unmatch payment: ' . $e->getMessage());
        }
    }

    /**
     * Auto-match all unmatched payments
     */
    public function autoMatch(Request $request)
    {
        $user = Auth::user();

        $dateFrom = $request->input('date_from', Carbon::now()->subDays(30)->toDateString());
        $dateTo = $request->input('date_to', Carbon::today()->toDateString());

        $unmatchedPayments = $this->reconciliationService->getUnmatchedPayments($user, [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);

        $matched = 0;
        $failed = 0;

        foreach ($unmatchedPayments as $payment) {
            try {
                $invoice = $this->reconciliationService->autoMatchPayment($payment);
                if ($invoice) {
                    $matched++;
                }
            } catch (\Exception $e) {
                $failed++;
            }
        }

        return redirect()->back()
            ->with('success', "Auto-matched {$matched} payments. {$failed} failed.");
    }

    /**
     * Get suggestions for a payment
     */
    public function suggestions(Payment $payment)
    {
        $user = Auth::user();

        if ($payment->user_id !== $user->id) {
            abort(403);
        }

        $suggestions = $this->reconciliationService->suggestMatches($payment, 10);

        return response()->json($suggestions);
    }

    /**
     * Generate reconciliation report
     */
    public function report(Request $request)
    {
        $user = Auth::user();

        $startDate = Carbon::parse($request->input('start_date', Carbon::now()->startOfMonth()));
        $endDate = Carbon::parse($request->input('end_date', Carbon::now()->endOfMonth()));

        $report = $this->reconciliationService->generateReconciliationReport($user, $startDate, $endDate);

        if ($request->wantsJson()) {
            return response()->json($report);
        }

        return view('reconciliation.report', compact('report', 'startDate', 'endDate'));
    }
}
