@extends('layouts.app')

@section('title', 'Invoices')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- Header -->
    <div class="mb-8 animate-fade-in">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h2 class="text-3xl font-bold text-gray-900 flex items-center">
                    <div class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center mr-4 shadow-lg">
                        <i class="fas fa-file-invoice text-white text-xl"></i>
                    </div>
                    Invoices
                </h2>
                <p class="mt-2 text-gray-600">Manage your invoices and track payments</p>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('dashboard') }}" class="bg-gradient-to-r from-gray-500 to-gray-600 text-white px-6 py-3 rounded-xl font-medium hover:shadow-lg transition-all duration-200">
                    <i class="fas fa-arrow-left mr-2"></i>Back
                </a>
                <a href="{{ route('invoices.create') }}" class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-6 py-3 rounded-xl font-medium hover:shadow-lg transition-all duration-200 transform hover:scale-105">
                    <i class="fas fa-plus mr-2"></i>New Invoice
                </a>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl p-6 border border-gray-200/50">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">Total Invoices</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $invoices->total() }}</p>
                </div>
                <div class="w-16 h-16 bg-indigo-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-file-invoice text-indigo-600 text-2xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl p-6 border border-gray-200/50">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">Unpaid</p>
                    <p class="text-3xl font-bold text-yellow-600">{{ $invoices->whereIn('status', ['sent', 'viewed', 'partial', 'overdue'])->count() }}</p>
                </div>
                <div class="w-16 h-16 bg-yellow-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-exclamation-circle text-yellow-600 text-2xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl p-6 border border-gray-200/50">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">Paid</p>
                    <p class="text-3xl font-bold text-green-600">{{ $invoices->where('status', 'paid')->count() }}</p>
                </div>
                <div class="w-16 h-16 bg-green-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl p-6 border border-gray-200/50">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">Total Amount</p>
                    <p class="text-3xl font-bold text-blue-600">KES {{ number_format($invoices->sum('total_amount'), 2) }}</p>
                </div>
                <div class="w-16 h-16 bg-blue-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-money-bill-wave text-blue-600 text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl p-6 mb-8 border border-gray-200/50">
        <form method="GET" action="{{ route('invoices.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Invoice #, reference..." class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    <option value="">All</option>
                    <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="sent" {{ request('status') === 'sent' ? 'selected' : '' }}>Sent</option>
                    <option value="viewed" {{ request('status') === 'viewed' ? 'selected' : '' }}>Viewed</option>
                    <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>Paid</option>
                    <option value="partial" {{ request('status') === 'partial' ? 'selected' : '' }}>Partial</option>
                    <option value="overdue" {{ request('status') === 'overdue' ? 'selected' : '' }}>Overdue</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">From Date</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            </div>
            <div class="flex items-end space-x-2">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2">To Date</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                </div>
                <button type="submit" class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-6 py-2 rounded-xl font-medium hover:shadow-lg transition-all duration-200">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </form>
    </div>

    <!-- Invoices Table -->
    <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl border border-gray-200/50 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase">Invoice #</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase">Customer</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase">Issue Date</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase">Due Date</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase">Amount</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase">Balance</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase">Status</th>
                        <th class="px-6 py-4 text-right text-xs font-semibold text-gray-700 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($invoices as $invoice)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-semibold text-gray-900">{{ $invoice->invoice_number }}</div>
                                @if($invoice->reference)
                                    <div class="text-xs text-gray-500">Ref: {{ $invoice->reference }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">{{ $invoice->customer->name ?? 'N/A' }}</div>
                                @if($invoice->customer && $invoice->customer->company)
                                    <div class="text-xs text-gray-500">{{ $invoice->customer->company }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $invoice->issue_date->format('M d, Y') }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $invoice->due_date->format('M d, Y') }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">KES {{ number_format($invoice->total_amount, 2) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold {{ $invoice->balance > 0 ? 'text-red-600' : 'text-green-600' }}">
                                KES {{ number_format($invoice->balance, 2) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($invoice->status === 'paid')
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        <i class="fas fa-check-circle mr-1"></i>Paid
                                    </span>
                                @elseif($invoice->status === 'overdue')
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                        <i class="fas fa-exclamation-circle mr-1"></i>Overdue
                                    </span>
                                @elseif($invoice->status === 'partial')
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                        <i class="fas fa-clock mr-1"></i>Partial
                                    </span>
                                @elseif($invoice->status === 'sent')
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                        <i class="fas fa-paper-plane mr-1"></i>Sent
                                    </span>
                                @elseif($invoice->status === 'viewed')
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-indigo-100 text-indigo-800">
                                        <i class="fas fa-eye mr-1"></i>Viewed
                                    </span>
                                @else
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                        <i class="fas fa-file mr-1"></i>{{ ucfirst($invoice->status) }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end space-x-2">
                                    <a href="{{ route('invoices.show', $invoice) }}" class="text-blue-600 hover:text-blue-900" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="{{ route('invoices.pdf', $invoice) }}" class="text-purple-600 hover:text-purple-900" title="Download PDF">
                                        <i class="fas fa-download"></i>
                                    </a>
                                    @if($invoice->status !== 'paid' && $invoice->status !== 'cancelled')
                                        <a href="{{ route('invoices.edit', $invoice) }}" class="text-indigo-600 hover:text-indigo-900" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center">
                                <div class="text-gray-500">
                                    <i class="fas fa-file-invoice text-4xl mb-4"></i>
                                    <p class="text-lg font-medium">No invoices found</p>
                                    <p class="text-sm mt-2">Get started by creating your first invoice</p>
                                    <a href="{{ route('invoices.create') }}" class="mt-4 inline-block bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-6 py-3 rounded-xl font-medium hover:shadow-lg transition-all duration-200">
                                        <i class="fas fa-plus mr-2"></i>Create Invoice
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($invoices->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $invoices->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
