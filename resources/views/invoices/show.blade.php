@extends('layouts.app')

@section('title', $invoice->invoice_number)

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- Header -->
    <div class="mb-8 animate-fade-in">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h2 class="text-3xl font-bold text-gray-900 flex items-center">
                    <div class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center mr-4 shadow-lg">
                        <i class="fas fa-file-invoice text-white text-xl"></i>
                    </div>
                    {{ $invoice->invoice_number }}
                </h2>
                <p class="mt-2 text-gray-600">
                    @if($invoice->customer)
                        Invoice for {{ $invoice->customer->name }}
                    @else
                        Invoice Details
                    @endif
                </p>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('invoices.index') }}" class="bg-gradient-to-r from-gray-500 to-gray-600 text-white px-6 py-3 rounded-xl font-medium hover:shadow-lg transition-all duration-200">
                    <i class="fas fa-arrow-left mr-2"></i>Back
                </a>
                <a href="{{ route('invoices.pdf', $invoice) }}" class="bg-gradient-to-r from-purple-600 to-pink-600 text-white px-6 py-3 rounded-xl font-medium hover:shadow-lg transition-all duration-200">
                    <i class="fas fa-download mr-2"></i>Download PDF
                </a>
                @if($invoice->status !== 'paid' && $invoice->status !== 'cancelled')
                    <a href="{{ route('invoices.edit', $invoice) }}" class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-6 py-3 rounded-xl font-medium hover:shadow-lg transition-all duration-200">
                        <i class="fas fa-edit mr-2"></i>Edit
                    </a>
                @endif
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Invoice Details -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Invoice Header -->
            <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl border border-gray-200/50 p-8">
                <div class="grid grid-cols-2 gap-8 mb-8">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 mb-4">From</h3>
                        <p class="text-sm font-semibold text-gray-900">{{ auth()->user()->name }}</p>
                        <p class="text-sm text-gray-600">{{ auth()->user()->email }}</p>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 mb-4">To</h3>
                        @if($invoice->customer)
                            <p class="text-sm font-semibold text-gray-900">{{ $invoice->customer->name }}</p>
                            @if($invoice->customer->company)
                                <p class="text-sm text-gray-600">{{ $invoice->customer->company }}</p>
                            @endif
                            @if($invoice->customer->email)
                                <p class="text-sm text-gray-600">{{ $invoice->customer->email }}</p>
                            @endif
                            @if($invoice->customer->phone)
                                <p class="text-sm text-gray-600">{{ $invoice->customer->phone }}</p>
                            @endif
                        @else
                            <p class="text-sm text-gray-600">No customer assigned</p>
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4 pt-6 border-t border-gray-200">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Invoice Number</p>
                        <p class="text-sm font-semibold text-gray-900">{{ $invoice->invoice_number }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Issue Date</p>
                        <p class="text-sm font-semibold text-gray-900">{{ $invoice->issue_date->format('M d, Y') }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Due Date</p>
                        <p class="text-sm font-semibold text-gray-900">{{ $invoice->due_date->format('M d, Y') }}</p>
                    </div>
                </div>
            </div>

            <!-- Invoice Items -->
            <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl border border-gray-200/50 p-8">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Items</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Description</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 uppercase">Quantity</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 uppercase">Unit Price</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 uppercase">Total</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($invoice->items as $item)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $item->description }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 text-right">{{ number_format($item->quantity, 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 text-right">KES {{ number_format($item->unit_price, 2) }}</td>
                                    <td class="px-4 py-3 text-sm font-semibold text-gray-900 text-right">KES {{ number_format($item->total, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Totals -->
                <div class="mt-6 flex justify-end">
                    <div class="w-full md:w-1/2">
                        <div class="space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Subtotal:</span>
                                <span class="font-semibold text-gray-900">KES {{ number_format($invoice->subtotal, 2) }}</span>
                            </div>
                            @if($invoice->discount_amount > 0)
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Discount:</span>
                                    <span class="font-semibold text-red-600">-KES {{ number_format($invoice->discount_amount, 2) }}</span>
                                </div>
                            @endif
                            @if($invoice->tax_amount > 0)
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Tax ({{ $invoice->tax_rate }}%):</span>
                                    <span class="font-semibold text-gray-900">KES {{ number_format($invoice->tax_amount, 2) }}</span>
                                </div>
                            @endif
                            <div class="flex justify-between text-lg font-bold pt-4 border-t border-gray-200">
                                <span class="text-gray-900">Total:</span>
                                <span class="text-indigo-600">KES {{ number_format($invoice->total_amount, 2) }}</span>
                            </div>
                            @if($invoice->paid_amount > 0)
                                <div class="flex justify-between text-sm pt-2">
                                    <span class="text-gray-600">Paid:</span>
                                    <span class="font-semibold text-green-600">KES {{ number_format($invoice->paid_amount, 2) }}</span>
                                </div>
                                <div class="flex justify-between text-sm font-bold">
                                    <span class="text-gray-900">Balance:</span>
                                    <span class="text-red-600">KES {{ number_format($invoice->balance, 2) }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment History -->
            @if($invoice->payments->count() > 0)
                <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl border border-gray-200/50 p-8">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Payment History</h3>
                    <div class="space-y-3">
                        @foreach($invoice->payments as $payment)
                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">KES {{ number_format($payment->amount, 2) }}</p>
                                    <p class="text-xs text-gray-600">{{ $payment->payment_date->format('M d, Y') }}</p>
                                </div>
                                <div class="text-sm text-gray-600">
                                    Transaction: {{ $payment->payment->transaction_id ?? 'N/A' }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Notes & Terms -->
            @if($invoice->notes || $invoice->terms)
                <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl border border-gray-200/50 p-8">
                    @if($invoice->notes)
                        <div class="mb-4">
                            <h3 class="text-sm font-semibold text-gray-700 mb-2">Notes</h3>
                            <p class="text-sm text-gray-600">{{ $invoice->notes }}</p>
                        </div>
                    @endif
                    @if($invoice->terms)
                        <div>
                            <h3 class="text-sm font-semibold text-gray-700 mb-2">Payment Terms</h3>
                            <p class="text-sm text-gray-600">{{ $invoice->terms }}</p>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="lg:col-span-1 space-y-6">
            <!-- Status Card -->
            <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl border border-gray-200/50 p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Status</h3>
                <div class="mb-4">
                    @if($invoice->status === 'paid')
                        <span class="px-4 py-2 inline-flex text-sm font-semibold rounded-full bg-green-100 text-green-800">
                            <i class="fas fa-check-circle mr-2"></i>Paid
                        </span>
                    @elseif($invoice->status === 'overdue')
                        <span class="px-4 py-2 inline-flex text-sm font-semibold rounded-full bg-red-100 text-red-800">
                            <i class="fas fa-exclamation-circle mr-2"></i>Overdue
                        </span>
                    @elseif($invoice->status === 'partial')
                        <span class="px-4 py-2 inline-flex text-sm font-semibold rounded-full bg-yellow-100 text-yellow-800">
                            <i class="fas fa-clock mr-2"></i>Partial Payment
                        </span>
                    @elseif($invoice->status === 'sent')
                        <span class="px-4 py-2 inline-flex text-sm font-semibold rounded-full bg-blue-100 text-blue-800">
                            <i class="fas fa-paper-plane mr-2"></i>Sent
                        </span>
                    @elseif($invoice->status === 'viewed')
                        <span class="px-4 py-2 inline-flex text-sm font-semibold rounded-full bg-indigo-100 text-indigo-800">
                            <i class="fas fa-eye mr-2"></i>Viewed
                        </span>
                    @else
                        <span class="px-4 py-2 inline-flex text-sm font-semibold rounded-full bg-gray-100 text-gray-800">
                            <i class="fas fa-file mr-2"></i>{{ ucfirst($invoice->status) }}
                        </span>
                    @endif
                </div>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Total Amount:</span>
                        <span class="font-semibold text-gray-900">KES {{ number_format($invoice->total_amount, 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Paid:</span>
                        <span class="font-semibold text-green-600">KES {{ number_format($invoice->paid_amount, 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Balance:</span>
                        <span class="font-semibold text-red-600">KES {{ number_format($invoice->balance, 2) }}</span>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl border border-gray-200/50 p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Actions</h3>
                <div class="space-y-3">
                    @if($invoice->status === 'draft')
                        <form action="{{ route('invoices.mark-sent', $invoice) }}" method="POST" class="inline-block w-full">
                            @csrf
                            <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-cyan-600 text-white px-4 py-2 rounded-xl font-medium hover:shadow-lg transition-all duration-200">
                                <i class="fas fa-paper-plane mr-2"></i>Mark as Sent
                            </button>
                        </form>
                    @endif

                    @if($invoice->customer && $invoice->customer->email && $invoice->status !== 'paid')
                        <form action="{{ route('invoices.send-email', $invoice) }}" method="POST" class="inline-block w-full">
                            @csrf
                            <button type="submit" class="w-full bg-gradient-to-r from-purple-600 to-pink-600 text-white px-4 py-2 rounded-xl font-medium hover:shadow-lg transition-all duration-200">
                                <i class="fas fa-envelope mr-2"></i>Send Email
                            </button>
                        </form>
                    @endif

                    @if($invoice->customer && $invoice->customer->phone && $invoice->status !== 'paid')
                        <form action="{{ route('invoices.send-sms', $invoice) }}" method="POST" class="inline-block w-full">
                            @csrf
                            <button type="submit" class="w-full bg-gradient-to-r from-green-600 to-emerald-600 text-white px-4 py-2 rounded-xl font-medium hover:shadow-lg transition-all duration-200">
                                <i class="fas fa-sms mr-2"></i>Send SMS
                            </button>
                        </form>
                    @endif

                    @if($invoice->status !== 'paid' && $invoice->status !== 'cancelled')
                        <form action="{{ route('invoices.cancel', $invoice) }}" method="POST" class="inline-block w-full" onsubmit="return confirm('Are you sure you want to cancel this invoice?');">
                            @csrf
                            <button type="submit" class="w-full bg-red-600 text-white px-4 py-2 rounded-xl font-medium hover:shadow-lg transition-all duration-200">
                                <i class="fas fa-times mr-2"></i>Cancel Invoice
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
