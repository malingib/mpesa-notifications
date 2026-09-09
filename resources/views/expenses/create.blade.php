@extends('layouts.app')

@section('title', 'Add Expense')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mb-8 animate-fade-in">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-3xl font-bold text-gray-900 flex items-center">
                    <div class="w-12 h-12 bg-gradient-to-br from-red-500 to-pink-600 rounded-xl flex items-center justify-center mr-4 shadow-lg">
                        <i class="fas fa-plus-circle text-white text-xl"></i>
                    </div>
                    Add Expense
                </h2>
            </div>
            <a href="{{ route('expenses.index') }}" class="bg-gradient-to-r from-gray-500 to-gray-600 text-white px-6 py-3 rounded-xl font-medium hover:shadow-lg transition-all duration-200">
                <i class="fas fa-arrow-left mr-2"></i>Back
            </a>
        </div>
    </div>

    <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl border border-gray-200/50 p-8">
        <form action="{{ route('expenses.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                    <select name="category_id" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500">
                        <option value="">Select Category</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Vendor</label>
                    <input type="text" name="vendor" value="{{ old('vendor') }}" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description *</label>
                    <input type="text" name="description" value="{{ old('description') }}" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Amount *</label>
                    <input type="number" name="amount" value="{{ old('amount') }}" step="0.01" min="0" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tax Amount</label>
                    <input type="number" name="tax_amount" value="{{ old('tax_amount', 0) }}" step="0.01" min="0" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Payment Method *</label>
                    <select name="payment_method" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500">
                        <option value="mpesa">M-Pesa</option>
                        <option value="bank">Bank Transfer</option>
                        <option value="cash">Cash</option>
                        <option value="card">Card</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Payment Reference</label>
                    <input type="text" name="payment_reference" value="{{ old('payment_reference') }}" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Expense Date *</label>
                    <input type="date" name="expense_date" value="{{ old('expense_date', date('Y-m-d')) }}" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Receipt</label>
                    <input type="file" name="receipt" accept="image/*,application/pdf" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                    <textarea name="notes" rows="3" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500">{{ old('notes') }}</textarea>
                </div>
            </div>
            <div class="mt-8 flex items-center justify-end space-x-4">
                <a href="{{ route('expenses.index') }}" class="px-6 py-3 border border-gray-300 rounded-xl font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
                <button type="submit" class="px-6 py-3 bg-gradient-to-r from-red-600 to-pink-600 text-white rounded-xl font-medium hover:shadow-lg transition-all duration-200">
                    <i class="fas fa-save mr-2"></i>Save Expense
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
