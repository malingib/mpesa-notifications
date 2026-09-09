<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class ExpenseController extends Controller
{
    /**
     * Display a listing of expenses
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        $query = Expense::where('user_id', $user->id)
            ->with('category');

        // Search
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('vendor', 'like', "%{$search}%")
                  ->orWhere('payment_reference', 'like', "%{$search}%");
            });
        }

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->where('expense_date', '>=', $request->input('date_from'));
        }
        if ($request->has('date_to')) {
            $query->where('expense_date', '<=', $request->input('date_to'));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $expenses = $query->orderBy('expense_date', 'desc')->paginate(20);
        $categories = ExpenseCategory::where(function ($q) use ($user) {
            $q->whereNull('user_id')->orWhere('user_id', $user->id);
        })->where('is_active', true)->orderBy('name')->get();

        return view('expenses.index', compact('expenses', 'categories'));
    }

    /**
     * Show the form for creating a new expense
     */
    public function create()
    {
        $user = Auth::user();
        $categories = ExpenseCategory::where(function ($q) use ($user) {
            $q->whereNull('user_id')->orWhere('user_id', $user->id);
        })->where('is_active', true)->orderBy('name')->get();

        return view('expenses.create', compact('categories'));
    }

    /**
     * Store a newly created expense
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'nullable|exists:expense_categories,id',
            'vendor' => 'nullable|string|max:255',
            'description' => 'required|string|max:500',
            'amount' => 'required|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'required|in:mpesa,bank,cash,card,other',
            'payment_reference' => 'nullable|string|max:255',
            'expense_date' => 'required|date',
            'receipt' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'status' => 'nullable|in:pending,paid,reimbursed',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $user = Auth::user();

        // Calculate total amount
        $amount = $request->input('amount');
        $taxAmount = $request->input('tax_amount', 0);
        $totalAmount = $amount + $taxAmount;

        // Handle receipt upload
        $receiptPath = null;
        if ($request->hasFile('receipt')) {
            $receiptPath = $request->file('receipt')->store('expenses/receipts', 'public');
        }

        $expense = Expense::create([
            'user_id' => $user->id,
            'category_id' => $request->input('category_id'),
            'vendor' => $request->input('vendor'),
            'description' => $request->input('description'),
            'amount' => $amount,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'payment_method' => $request->input('payment_method'),
            'payment_reference' => $request->input('payment_reference'),
            'expense_date' => $request->input('expense_date'),
            'receipt_path' => $receiptPath,
            'status' => $request->input('status', 'paid'),
            'notes' => $request->input('notes'),
        ]);

        return redirect()->route('expenses.index')
            ->with('success', 'Expense created successfully.');
    }

    /**
     * Display the specified expense
     */
    public function show(Expense $expense)
    {
        $user = Auth::user();
        
        if ($expense->user_id !== $user->id) {
            abort(403);
        }

        $expense->load('category');

        return view('expenses.show', compact('expense'));
    }

    /**
     * Show the form for editing the specified expense
     */
    public function edit(Expense $expense)
    {
        $user = Auth::user();
        
        if ($expense->user_id !== $user->id) {
            abort(403);
        }

        $categories = ExpenseCategory::where(function ($q) use ($user) {
            $q->whereNull('user_id')->orWhere('user_id', $user->id);
        })->where('is_active', true)->orderBy('name')->get();

        return view('expenses.edit', compact('expense', 'categories'));
    }

    /**
     * Update the specified expense
     */
    public function update(Request $request, Expense $expense)
    {
        $user = Auth::user();
        
        if ($expense->user_id !== $user->id) {
            abort(403);
        }

        $validator = Validator::make($request->all(), [
            'category_id' => 'nullable|exists:expense_categories,id',
            'vendor' => 'nullable|string|max:255',
            'description' => 'required|string|max:500',
            'amount' => 'required|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'required|in:mpesa,bank,cash,card,other',
            'payment_reference' => 'nullable|string|max:255',
            'expense_date' => 'required|date',
            'receipt' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'status' => 'nullable|in:pending,paid,reimbursed',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Calculate total amount
        $amount = $request->input('amount');
        $taxAmount = $request->input('tax_amount', 0);
        $totalAmount = $amount + $taxAmount;

        // Handle receipt upload
        $receiptPath = $expense->receipt_path;
        if ($request->hasFile('receipt')) {
            // Delete old receipt
            if ($receiptPath && Storage::disk('public')->exists($receiptPath)) {
                Storage::disk('public')->delete($receiptPath);
            }
            $receiptPath = $request->file('receipt')->store('expenses/receipts', 'public');
        }

        $expense->update([
            'category_id' => $request->input('category_id'),
            'vendor' => $request->input('vendor'),
            'description' => $request->input('description'),
            'amount' => $amount,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'payment_method' => $request->input('payment_method'),
            'payment_reference' => $request->input('payment_reference'),
            'expense_date' => $request->input('expense_date'),
            'receipt_path' => $receiptPath,
            'status' => $request->input('status', 'paid'),
            'notes' => $request->input('notes'),
        ]);

        return redirect()->route('expenses.show', $expense)
            ->with('success', 'Expense updated successfully.');
    }

    /**
     * Remove the specified expense
     */
    public function destroy(Expense $expense)
    {
        $user = Auth::user();
        
        if ($expense->user_id !== $user->id) {
            abort(403);
        }

        // Delete receipt file
        if ($expense->receipt_path && Storage::disk('public')->exists($expense->receipt_path)) {
            Storage::disk('public')->delete($expense->receipt_path);
        }

        $expense->delete();

        return redirect()->route('expenses.index')
            ->with('success', 'Expense deleted successfully.');
    }

    /**
     * Download expense receipt
     */
    public function downloadReceipt(Expense $expense)
    {
        $user = Auth::user();
        
        if ($expense->user_id !== $user->id) {
            abort(403);
        }

        if (!$expense->receipt_path || !Storage::disk('public')->exists($expense->receipt_path)) {
            abort(404, 'Receipt not found.');
        }

        return Storage::disk('public')->download($expense->receipt_path);
    }
}
