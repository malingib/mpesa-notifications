@extends('layouts.app')

@section('title', 'Expense Details')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mb-8 animate-fade-in">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-3xl font-bold text-gray-900 flex items-center">
                    <div class="w-12 h-12 bg-gradient-to-br from-red-500 to-pink-600 rounded-xl flex items-center justify-center mr-4 shadow-lg">
                        <i class="fas fa-receipt text-white text-xl"></i>
                    </div>
                    Expense Details
                </h2>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('expenses.index') }}" class="bg-gradient-to-r from-gray-500 to-gray-600 text-white px-6 py-3 rounded-xl font-medium hover:shadow-lg transition-all duration-200">
                    <i class="fas fa-arrow-left mr-2"></i>Back
                </a>
                <a href="{{ route('expenses.edit', $expense) }}" class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-6 py-3 rounded-xl font-medium hover:shadow-lg transition-all duration-200">
                    <i class="fas fa-edit mr-2"></i>Edit
                </a>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2">
            <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl border border-gray-200/50 p-8">
                <h3 class="text-lg font-bold text-gray-900 mb-6">{{ $expense->description }}</h3>
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Amount</p>
                        <p class="text-2xl font-bold text-red-600">KES {{ number_format($expense->total_amount, 2) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Date</p>
                        <p class="text-lg font-semibold text-gray-900">{{ $expense->expense_date->format('M d, Y') }}</p>
                    </div>
                    @if($expense->category)
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Category</p>
                            <span class="px-3 py-1 inline-flex text-sm font-semibold rounded-full bg-blue-100 text-blue-800">{{ $expense->category->name }}</span>
                        </div>
                    @endif
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Payment Method</p>
                        <p class="text-sm font-semibold text-gray-900 capitalize">{{ $expense->payment_method }}</p>
                    </div>
                    @if($expense->vendor)
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Vendor</p>
                            <p class="text-sm font-semibold text-gray-900">{{ $expense->vendor }}</p>
                        </div>
                    @endif
                    @if($expense->payment_reference)
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Payment Reference</p>
                            <p class="text-sm font-semibold text-gray-900">{{ $expense->payment_reference }}</p>
                        </div>
                    @endif
                </div>
                @if($expense->notes)
                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <p class="text-sm text-gray-600 mb-2">Notes</p>
                        <p class="text-sm text-gray-900">{{ $expense->notes }}</p>
                    </div>
                @endif
            </div>
        </div>
        <div>
            <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl border border-gray-200/50 p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Actions</h3>
                <div class="space-y-3">
                    @if($expense->receipt_path)
                        <a href="{{ route('expenses.receipt', $expense) }}" class="block w-full text-center bg-gradient-to-r from-purple-600 to-pink-600 text-white px-4 py-3 rounded-xl font-medium hover:shadow-lg transition-all duration-200">
                            <i class="fas fa-download mr-2"></i>Download Receipt
                        </a>
                    @endif
                    <form action="{{ route('expenses.destroy', $expense) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this expense?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="w-full bg-red-600 text-white px-4 py-3 rounded-xl font-medium hover:shadow-lg transition-all duration-200">
                            <i class="fas fa-trash mr-2"></i>Delete Expense
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
