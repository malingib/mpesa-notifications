@extends('layouts.app')

@section('title', $customer->name)

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- Header -->
    <div class="mb-8 animate-fade-in">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h2 class="text-3xl font-bold text-gray-900 flex items-center">
                    <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-cyan-600 rounded-xl flex items-center justify-center mr-4 shadow-lg">
                        <i class="fas fa-user text-white text-xl"></i>
                    </div>
                    {{ $customer->name }}
                </h2>
                <p class="mt-2 text-gray-600">{{ $customer->company ?? 'Customer Details' }}</p>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('customers.index') }}" class="bg-gradient-to-r from-gray-500 to-gray-600 text-white px-6 py-3 rounded-xl font-medium hover:shadow-lg transition-all duration-200">
                    <i class="fas fa-arrow-left mr-2"></i>Back
                </a>
                <a href="{{ route('customers.edit', $customer) }}" class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-6 py-3 rounded-xl font-medium hover:shadow-lg transition-all duration-200">
                    <i class="fas fa-edit mr-2"></i>Edit
                </a>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Customer Info -->
        <div class="lg:col-span-1">
            <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl border border-gray-200/50 p-6 mb-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Customer Information</h3>
                <div class="space-y-4">
                    @if($customer->email)
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Email</p>
                            <p class="text-sm font-medium text-gray-900">{{ $customer->email }}</p>
                        </div>
                    @endif
                    @if($customer->phone)
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Phone</p>
                            <p class="text-sm font-medium text-gray-900">{{ $customer->phone }}</p>
                        </div>
                    @endif
                    @if($customer->tax_id)
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Tax ID</p>
                            <p class="text-sm font-medium text-gray-900">{{ $customer->tax_id }}</p>
                        </div>
                    @endif
                    @if($customer->address)
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Address</p>
                            <p class="text-sm font-medium text-gray-900">{{ $customer->address }}</p>
                            @if($customer->city)
                                <p class="text-sm text-gray-900">{{ $customer->city }}, {{ $customer->country }}</p>
                            @endif
                        </div>
                    @endif
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Status</p>
                        @if($customer->status === 'active')
                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                <i class="fas fa-check-circle mr-1"></i>Active
                            </span>
                        @elseif($customer->status === 'inactive')
                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                <i class="fas fa-pause-circle mr-1"></i>Inactive
                            </span>
                        @else
                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                <i class="fas fa-archive mr-1"></i>Archived
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl border border-gray-200/50 p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Statistics</h3>
                <div class="space-y-4">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Total Invoices</p>
                        <p class="text-2xl font-bold text-gray-900">{{ $customer->invoices->count() }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Total Owed</p>
                        <p class="text-2xl font-bold text-red-600">KES {{ number_format($customer->total_owed ?? 0, 2) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Total Paid</p>
                        <p class="text-2xl font-bold text-green-600">KES {{ number_format($customer->total_paid ?? 0, 2) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Invoices -->
        <div class="lg:col-span-2">
            <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl border border-gray-200/50 p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-bold text-gray-900">Recent Invoices</h3>
                    <a href="{{ route('invoices.create', ['customer_id' => $customer->id]) }}" class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-4 py-2 rounded-xl text-sm font-medium hover:shadow-lg transition-all duration-200">
                        <i class="fas fa-plus mr-2"></i>New Invoice
                    </a>
                </div>

                @if($customer->invoices->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Invoice #</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Amount</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Status</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($customer->invoices as $invoice)
                                    <tr>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ $invoice->invoice_number }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">{{ $invoice->issue_date->format('M d, Y') }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-gray-900">KES {{ number_format($invoice->total_amount, 2) }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            @if($invoice->status === 'paid')
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Paid</span>
                                            @elseif($invoice->status === 'overdue')
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Overdue</span>
                                            @else
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">{{ ucfirst($invoice->status) }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-right text-sm">
                                            <a href="{{ route('invoices.show', $invoice) }}" class="text-blue-600 hover:text-blue-900">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-12">
                        <i class="fas fa-file-invoice text-4xl text-gray-400 mb-4"></i>
                        <p class="text-gray-600">No invoices yet</p>
                        <a href="{{ route('invoices.create', ['customer_id' => $customer->id]) }}" class="mt-4 inline-block bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-6 py-3 rounded-xl font-medium hover:shadow-lg transition-all duration-200">
                            <i class="fas fa-plus mr-2"></i>Create First Invoice
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
