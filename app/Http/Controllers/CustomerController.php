<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CustomerController extends Controller
{
    /**
     * Display a listing of customers
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        $query = Customer::where('user_id', $user->id)
            ->withCount('invoices');

        // Search
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('company', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $customers = $query->orderBy('name')->paginate(20);

        return view('customers.index', compact('customers'));
    }

    /**
     * Show the form for creating a new customer
     */
    public function create()
    {
        return view('customers.create');
    }

    /**
     * Store a newly created customer
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'tags' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $user = Auth::user();

        $customer = Customer::create([
            'user_id' => $user->id,
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'company' => $request->input('company'),
            'tax_id' => $request->input('tax_id'),
            'address' => $request->input('address'),
            'city' => $request->input('city'),
            'country' => $request->input('country', 'Kenya'),
            'notes' => $request->input('notes'),
            'tags' => $request->input('tags', []),
            'status' => 'active',
        ]);

        return redirect()->route('customers.index')
            ->with('success', 'Customer created successfully.');
    }

    /**
     * Display the specified customer
     */
    public function show(Customer $customer)
    {
        $user = Auth::user();
        
        // Ensure customer belongs to user
        if ($customer->user_id !== $user->id) {
            abort(403);
        }

        $customer->load(['invoices' => function ($query) {
            $query->orderBy('issue_date', 'desc')->limit(10);
        }]);

        return view('customers.show', compact('customer'));
    }

    /**
     * Show the form for editing the specified customer
     */
    public function edit(Customer $customer)
    {
        $user = Auth::user();
        
        if ($customer->user_id !== $user->id) {
            abort(403);
        }

        return view('customers.edit', compact('customer'));
    }

    /**
     * Update the specified customer
     */
    public function update(Request $request, Customer $customer)
    {
        $user = Auth::user();
        
        if ($customer->user_id !== $user->id) {
            abort(403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'tags' => 'nullable|array',
            'status' => 'required|in:active,inactive,archived',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $customer->update($request->only([
            'name', 'email', 'phone', 'company', 'tax_id',
            'address', 'city', 'country', 'notes', 'tags', 'status'
        ]));

        return redirect()->route('customers.show', $customer)
            ->with('success', 'Customer updated successfully.');
    }

    /**
     * Remove the specified customer
     */
    public function destroy(Customer $customer)
    {
        $user = Auth::user();
        
        if ($customer->user_id !== $user->id) {
            abort(403);
        }

        // Check if customer has invoices
        if ($customer->invoices()->count() > 0) {
            return redirect()->back()
                ->with('error', 'Cannot delete customer with existing invoices.');
        }

        $customer->delete();

        return redirect()->route('customers.index')
            ->with('success', 'Customer deleted successfully.');
    }
}
