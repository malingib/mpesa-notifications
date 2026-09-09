@extends('layouts.app')

@section('title', 'Edit Invoice')

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- Header -->
    <div class="mb-8 animate-fade-in">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-3xl font-bold text-gray-900 flex items-center">
                    <div class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center mr-4 shadow-lg">
                        <i class="fas fa-edit text-white text-xl"></i>
                    </div>
                    Edit Invoice: {{ $invoice->invoice_number }}
                </h2>
                <p class="mt-2 text-gray-600">Update invoice details</p>
            </div>
            <a href="{{ route('invoices.show', $invoice) }}" class="bg-gradient-to-r from-gray-500 to-gray-600 text-white px-6 py-3 rounded-xl font-medium hover:shadow-lg transition-all duration-200">
                <i class="fas fa-arrow-left mr-2"></i>Back
            </a>
        </div>
    </div>

    <!-- Form -->
    <form action="{{ route('invoices.update', $invoice) }}" method="POST" id="invoiceForm">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Main Form -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Customer Selection -->
                <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl border border-gray-200/50 p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Customer Information</h3>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Customer</label>
                        <select name="customer_id" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                            <option value="">Select Customer (Optional)</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}" {{ old('customer_id', $invoice->customer_id) == $customer->id ? 'selected' : '' }}>
                                    {{ $customer->name }} @if($customer->company)({{ $customer->company }})@endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Invoice Details -->
                <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl border border-gray-200/50 p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Invoice Details</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Issue Date *</label>
                            <input type="date" name="issue_date" value="{{ old('issue_date', $invoice->issue_date->format('Y-m-d')) }}" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Due Date *</label>
                            <input type="date" name="due_date" value="{{ old('due_date', $invoice->due_date->format('Y-m-d')) }}" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tax Rate (%)</label>
                            <input type="number" name="tax_rate" value="{{ old('tax_rate', $invoice->tax_rate) }}" step="0.01" min="0" max="100" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent" onchange="calculateTotals()">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Discount Amount</label>
                            <input type="number" name="discount_amount" value="{{ old('discount_amount', $invoice->discount_amount) }}" step="0.01" min="0" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent" onchange="calculateTotals()">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Reference Number</label>
                            <input type="text" name="reference" value="{{ old('reference', $invoice->reference) }}" placeholder="Payment reference" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Currency</label>
                            <select name="currency" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                <option value="KES" {{ old('currency', $invoice->currency) === 'KES' ? 'selected' : '' }}>KES</option>
                                <option value="USD" {{ old('currency', $invoice->currency) === 'USD' ? 'selected' : '' }}>USD</option>
                                <option value="EUR" {{ old('currency', $invoice->currency) === 'EUR' ? 'selected' : '' }}>EUR</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Invoice Items -->
                <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl border border-gray-200/50 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-900">Invoice Items</h3>
                        <button type="button" onclick="addItem()" class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-4 py-2 rounded-xl text-sm font-medium hover:shadow-lg transition-all duration-200">
                            <i class="fas fa-plus mr-2"></i>Add Item
                        </button>
                    </div>
                    <div id="itemsContainer" class="space-y-4">
                        <!-- Items will be populated from existing invoice -->
                    </div>
                </div>

                <!-- Notes & Terms -->
                <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl border border-gray-200/50 p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Additional Information</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                            <textarea name="notes" rows="3" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent">{{ old('notes', $invoice->notes) }}</textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Payment Terms</label>
                            <textarea name="terms" rows="2" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent">{{ old('terms', $invoice->terms) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Summary Sidebar -->
            <div class="lg:col-span-1">
                <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl border border-gray-200/50 p-6 sticky top-8">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Invoice Summary</h3>
                    <div class="space-y-4">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Subtotal:</span>
                            <span class="font-semibold text-gray-900" id="subtotal">KES {{ number_format($invoice->subtotal, 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Tax:</span>
                            <span class="font-semibold text-gray-900" id="tax">KES {{ number_format($invoice->tax_amount, 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Discount:</span>
                            <span class="font-semibold text-red-600" id="discount">KES {{ number_format($invoice->discount_amount, 2) }}</span>
                        </div>
                        <div class="border-t border-gray-200 pt-4">
                            <div class="flex justify-between">
                                <span class="text-lg font-bold text-gray-900">Total:</span>
                                <span class="text-lg font-bold text-indigo-600" id="total">KES {{ number_format($invoice->total_amount, 2) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 space-y-3">
                        <button type="submit" class="w-full bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-6 py-3 rounded-xl font-medium hover:shadow-lg transition-all duration-200 transform hover:scale-105">
                            <i class="fas fa-save mr-2"></i>Update Invoice
                        </button>
                        <a href="{{ route('invoices.show', $invoice) }}" class="block w-full text-center px-6 py-3 border border-gray-300 rounded-xl font-medium text-gray-700 hover:bg-gray-50 transition-all duration-200">
                            Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
let itemCount = 0;
const existingItems = @json($invoice->items);

function addItem(item = null) {
    itemCount++;
    const container = document.getElementById('itemsContainer');
    const itemHtml = `
        <div class="item-row bg-gray-50 rounded-xl p-4 border border-gray-200" data-item-index="${itemCount}">
            <div class="grid grid-cols-12 gap-4">
                <div class="col-span-12 md:col-span-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description *</label>
                    <input type="text" name="items[${itemCount}][description]" value="${item?.description || ''}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="col-span-4 md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Quantity</label>
                    <input type="number" name="items[${itemCount}][quantity]" value="${item?.quantity || 1}" step="0.01" min="0.01" required class="item-quantity w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" onchange="calculateTotals()">
                </div>
                <div class="col-span-4 md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Unit Price</label>
                    <input type="number" name="items[${itemCount}][unit_price]" value="${item?.unit_price || 0}" step="0.01" min="0" required class="item-price w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" onchange="calculateTotals()">
                </div>
                <div class="col-span-4 md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Actions</label>
                    <button type="button" onclick="removeItem(this)" class="w-full bg-red-500 text-white px-3 py-2 rounded-lg hover:bg-red-600 transition-colors">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', itemHtml);
    calculateTotals();
}

function removeItem(button) {
    button.closest('.item-row').remove();
    calculateTotals();
}

function calculateTotals() {
    let subtotal = 0;
    const items = document.querySelectorAll('.item-row');
    
    items.forEach(item => {
        const quantity = parseFloat(item.querySelector('.item-quantity')?.value || 0);
        const price = parseFloat(item.querySelector('.item-price')?.value || 0);
        subtotal += quantity * price;
    });

    const taxRate = parseFloat(document.querySelector('input[name="tax_rate"]')?.value || 0);
    const discount = parseFloat(document.querySelector('input[name="discount_amount"]')?.value || 0);
    
    const tax = (subtotal - discount) * (taxRate / 100);
    const total = subtotal - discount + tax;

    document.getElementById('subtotal').textContent = 'KES ' + subtotal.toFixed(2);
    document.getElementById('tax').textContent = 'KES ' + tax.toFixed(2);
    document.getElementById('discount').textContent = 'KES ' + discount.toFixed(2);
    document.getElementById('total').textContent = 'KES ' + total.toFixed(2);
}

// Load existing items
document.addEventListener('DOMContentLoaded', function() {
    existingItems.forEach(item => {
        addItem({
            description: item.description,
            quantity: item.quantity,
            unit_price: item.unit_price
        });
    });
    
    document.querySelector('input[name="tax_rate"]')?.addEventListener('input', calculateTotals);
    document.querySelector('input[name="discount_amount"]')?.addEventListener('input', calculateTotals);
});
</script>
@endsection
