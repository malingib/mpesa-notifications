<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Customer;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class InvoiceController extends Controller
{
    protected InvoiceService $invoiceService;

    public function __construct(InvoiceService $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }

    /**
     * Display a listing of invoices
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        $query = Invoice::where('user_id', $user->id)
            ->with(['customer', 'items']);

        // Search
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->where('issue_date', '>=', $request->input('date_from'));
        }
        if ($request->has('date_to')) {
            $query->where('issue_date', '<=', $request->input('date_to'));
        }

        $invoices = $query->orderBy('issue_date', 'desc')->paginate(20);

        return view('invoices.index', compact('invoices'));
    }

    /**
     * Show the form for creating a new invoice
     */
    public function create()
    {
        $user = Auth::user();
        $customers = Customer::where('user_id', $user->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return view('invoices.create', compact('customers'));
    }

    /**
     * Store a newly created invoice
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'nullable|exists:customers,id',
            'issue_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:issue_date',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'reference' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:500',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $user = Auth::user();

        // Verify customer belongs to user
        if ($request->has('customer_id')) {
            $customer = Customer::where('id', $request->input('customer_id'))
                ->where('user_id', $user->id)
                ->first();
            
            if (!$customer) {
                return redirect()->back()
                    ->with('error', 'Invalid customer selected.')
                    ->withInput();
            }
        }

        try {
            $invoice = $this->invoiceService->create($request->all(), $user);

            return redirect()->route('invoices.show', $invoice)
                ->with('success', 'Invoice created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to create invoice: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Display the specified invoice
     */
    public function show(Invoice $invoice)
    {
        $user = Auth::user();
        
        if ($invoice->user_id !== $user->id) {
            abort(403);
        }

        $invoice->load(['customer', 'items', 'payments.payment']);

        return view('invoices.show', compact('invoice'));
    }

    /**
     * Show the form for editing the specified invoice
     */
    public function edit(Invoice $invoice)
    {
        $user = Auth::user();
        
        if ($invoice->user_id !== $user->id) {
            abort(403);
        }

        if ($invoice->status === 'paid') {
            return redirect()->route('invoices.show', $invoice)
                ->with('error', 'Cannot edit a paid invoice.');
        }

        $customers = Customer::where('user_id', $user->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $invoice->load('items');

        return view('invoices.edit', compact('invoice', 'customers'));
    }

    /**
     * Update the specified invoice
     */
    public function update(Request $request, Invoice $invoice)
    {
        $user = Auth::user();
        
        if ($invoice->user_id !== $user->id) {
            abort(403);
        }

        if ($invoice->status === 'paid') {
            return redirect()->back()
                ->with('error', 'Cannot update a paid invoice.');
        }

        $validator = Validator::make($request->all(), [
            'customer_id' => 'nullable|exists:customers,id',
            'issue_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:issue_date',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'reference' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:500',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $invoice = $this->invoiceService->update($invoice, $request->all());

            return redirect()->route('invoices.show', $invoice)
                ->with('success', 'Invoice updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to update invoice: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Remove the specified invoice
     */
    public function destroy(Invoice $invoice)
    {
        $user = Auth::user();
        
        if ($invoice->user_id !== $user->id) {
            abort(403);
        }

        try {
            $this->invoiceService->delete($invoice);

            return redirect()->route('invoices.index')
                ->with('success', 'Invoice deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to delete invoice: ' . $e->getMessage());
        }
    }

    /**
     * Download invoice PDF
     */
    public function downloadPdf(Invoice $invoice)
    {
        $user = Auth::user();
        
        if ($invoice->user_id !== $user->id) {
            abort(403);
        }

        // Generate PDF if not exists
        if (!$invoice->pdf_path || !file_exists($invoice->pdf_path)) {
            $pdfPath = $this->invoiceService->generatePdf($invoice);
        } else {
            $pdfPath = $invoice->pdf_path;
        }

        return response()->download($pdfPath, $invoice->invoice_number . '.pdf');
    }

    /**
     * Send invoice via email
     */
    public function sendEmail(Request $request, Invoice $invoice)
    {
        $user = Auth::user();
        
        if ($invoice->user_id !== $user->id) {
            abort(403);
        }

        try {
            $this->invoiceService->sendEmail($invoice);

            return redirect()->back()
                ->with('success', 'Invoice sent via email successfully.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to send invoice: ' . $e->getMessage());
        }
    }

    /**
     * Send invoice via SMS
     */
    public function sendSms(Request $request, Invoice $invoice)
    {
        $user = Auth::user();
        
        if ($invoice->user_id !== $user->id) {
            abort(403);
        }

        try {
            $this->invoiceService->sendSms($invoice);

            return redirect()->back()
                ->with('success', 'Invoice sent via SMS successfully.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to send invoice: ' . $e->getMessage());
        }
    }

    /**
     * Mark invoice as sent
     */
    public function markAsSent(Invoice $invoice)
    {
        $user = Auth::user();
        
        if ($invoice->user_id !== $user->id) {
            abort(403);
        }

        $this->invoiceService->markAsSent($invoice);

        return redirect()->back()
            ->with('success', 'Invoice marked as sent.');
    }

    /**
     * Cancel invoice
     */
    public function cancel(Request $request, Invoice $invoice)
    {
        $user = Auth::user();
        
        if ($invoice->user_id !== $user->id) {
            abort(403);
        }

        try {
            $this->invoiceService->cancel($invoice, $request->input('reason'));

            return redirect()->route('invoices.show', $invoice)
                ->with('success', 'Invoice cancelled successfully.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to cancel invoice: ' . $e->getMessage());
        }
    }
}
