@extends('layouts.app')

@section('title', 'Expenses')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- Header -->
    <div class="mb-8 animate-fade-in">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h2 class="text-3xl font-bold text-gray-900 flex items-center">
                    <div class="w-12 h-12 bg-gradient-to-br from-red-500 to-pink-600 rounded-xl flex items-center justify-center mr-4 shadow-lg">
                        <i class="fas fa-receipt text-white text-xl"></i>
                    </div>
                    Expenses
                </h2>
                <p class="mt-2 text-gray-600">Track your business expenses</p>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('dashboard') }}" class="bg-gradient-to-r from-gray-500 to-gray-600 text-white px-6 py-3 rounded-xl font-medium hover:shadow-lg transition-all duration-200">
                    <i class="fas fa-arrow-left mr-2"></i>Back
                </a>
                <a href="{{ route('expenses.create') }}" class="bg-gradient-to-r from-red-600 to-pink-600 text-white px-6 py-3 rounded-xl font-medium hover:shadow-lg transition-all duration-200 transform hover:scale-105">
                    <i class="fas fa-plus mr-2"></i>Add Expense
                </a>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl p-6 mb-8 border border-gray-200/50">
        <form method="GET" action="{{ route('expenses.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Description, vendor..." class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                <select name="category_id" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500">
                    <option value="">All Categories</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">From Date</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500">
            </div>
            <div class="flex items-end space-x-2">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2">To Date</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500">
                </div>
                <button type="submit" class="bg-gradient-to-r from-red-600 to-pink-600 text-white px-6 py-2 rounded-xl font-medium hover:shadow-lg transition-all duration-200">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </form>
    </div>

    <!-- Expenses Table -->
    <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl border border-gray-200/50 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase">Date</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase">Description</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase">Category</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase">Vendor</th>
                        <th class="px-6 py-4 text-right text-xs font-semibold text-gray-700 uppercase">Amount</th>
                        <th class="px-6 py-4 text-right text-xs font-semibold text-gray-700 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($expenses as $expense)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $expense->expense_date->format('M d, Y') }}</td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">{{ $expense->description }}</div>
                                @if($expense->payment_reference)
                                    <div class="text-xs text-gray-500">Ref: {{ $expense->payment_reference }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($expense->category)
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">{{ $expense->category->name }}</span>
                                @else
                                    <span class="text-xs text-gray-500">Uncategorized</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $expense->vendor ?? 'N/A' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-red-600 text-right">KES {{ number_format($expense->total_amount, 2) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end space-x-2">
                                    <a href="{{ route('expenses.show', $expense) }}" class="text-blue-600 hover:text-blue-900" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="{{ route('expenses.edit', $expense) }}" class="text-indigo-600 hover:text-indigo-900" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    @if($expense->receipt_path)
                                        <a href="{{ route('expenses.receipt', $expense) }}" class="text-purple-600 hover:text-purple-900" title="Download Receipt">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <div class="text-gray-500">
                                    <i class="fas fa-receipt text-4xl mb-4"></i>
                                    <p class="text-lg font-medium">No expenses found</p>
                                    <a href="{{ route('expenses.create') }}" class="mt-4 inline-block bg-gradient-to-r from-red-600 to-pink-600 text-white px-6 py-3 rounded-xl font-medium hover:shadow-lg transition-all duration-200">
                                        <i class="fas fa-plus mr-2"></i>Add Expense
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($expenses->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $expenses->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
