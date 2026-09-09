@extends('layouts.app')

@section('title', 'Payment Reconciliation')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- Header -->
    <div class="mb-8 animate-fade-in">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h2 class="text-3xl font-bold text-gray-900 flex items-center">
                    <div class="w-12 h-12 bg-gradient-to-br from-orange-500 to-red-600 rounded-xl flex items-center justify-center mr-4 shadow-lg">
                        <i class="fas fa-link text-white text-xl"></i>
                    </div>
                    Payment Reconciliation
                </h2>
                <p class="mt-2 text-gray-600">Match payments to invoices automatically or manually</p>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('dashboard') }}" class="bg-gradient-to-r from-gray-500 to-gray-600 text-white px-6 py-3 rounded-xl font-medium hover:shadow-lg transition-all duration-200">
                    <i class="fas fa-arrow-left mr-2"></i>Back
                </a>
                <form action="{{ route('reconciliation.auto-match') }}" method="POST" class="inline">
                    @csrf
                    <input type="hidden" name="date_from" value="{{ $dateFrom }}">
                    <input type="hidden" name="date_to" value="{{ $dateTo }}">
                    <button type="submit" class="bg-gradient-to-r from-orange-600 to-red-600 text-white px-6 py-3 rounded-xl font-medium hover:shadow-lg transition-all duration-200">
                        <i class="fas fa-magic mr-2"></i>Auto-Match All
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl p-6 mb-8 border border-gray-200/50">
        <form method="GET" action="{{ route('reconciliation.index') }}" class="flex items-end space-x-4">
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-2">From Date</label>
                <input type="date" name="date_from" value="{{ $dateFrom }}" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500">
            </div>
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-2">To Date</label>
                <input type="date" name="date_to" value="{{ $dateTo }}" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500">
            </div>
            <div>
                <button type="submit" class="bg-gradient-to-r from-orange-600 to-red-600 text-white px-6 py-2 rounded-xl font-medium hover:shadow-lg transition-all duration-200">
                    <i class="fas fa-filter mr-2"></i>Filter
                </button>
            </div>
        </form>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Unmatched Payments -->
        <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl border border-gray-200/50">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-bold text-gray-900 flex items-center">
                    <i class="fas fa-exclamation-triangle text-orange-500 mr-2"></i>
                    Unmatched Payments ({{ $unmatchedPayments->count() }})
                </h3>
            </div>
            <div class="p-6 max-h-96 overflow-y-auto">
                @forelse($unmatchedPayments as $payment)
                    <div class="mb-4 p-4 bg-gray-50 rounded-xl border border-gray-200">
                        <div class="flex items-center justify-between mb-2">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">KES {{ number_format($payment->amount, 2) }}</p>
                                <p class="text-xs text-gray-600">{{ $payment->transaction_time->format('M d, Y H:i') }}</p>
                            </div>
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-orange-100 text-orange-800">Unmatched</span>
                        </div>
                        <div class="text-xs text-gray-600 mb-3">
                            <p>Transaction: {{ $payment->transaction_id }}</p>
                            @if($payment->phone_number)
                                <p>Phone: {{ $payment->phone_number }}</p>
                            @endif
                        </div>
                        @if($payment->suggestions && $payment->suggestions->count() > 0)
                            <div class="mb-3">
                                <p class="text-xs font-semibold text-gray-700 mb-2">Suggested Matches:</p>
                                <div class="space-y-2">
                                    @foreach($payment->suggestions->take(3) as $suggestion)
                                        <form action="{{ route('reconciliation.match') }}" method="POST" class="inline-block w-full">
                                            @csrf
                                            <input type="hidden" name="payment_id" value="{{ $payment->id }}">
                                            <input type="hidden" name="invoice_id" value="{{ $suggestion->id }}">
                                            <input type="hidden" name="amount" value="{{ min($payment->amount, $suggestion->balance) }}">
                                            <button type="submit" class="w-full text-left p-2 bg-blue-50 hover:bg-blue-100 rounded-lg text-xs transition-colors">
                                                <span class="font-semibold">{{ $suggestion->invoice_number }}</span> - 
                                                Balance: KES {{ number_format($suggestion->balance, 2) }}
                                            </button>
                                        </form>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        <button onclick="showMatchModal({{ $payment->id }}, {{ $payment->amount }})" class="w-full bg-gradient-to-r from-orange-600 to-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:shadow-lg transition-all duration-200">
                            <i class="fas fa-link mr-2"></i>Match Manually
                        </button>
                    </div>
                @empty
                    <div class="text-center py-12">
                        <i class="fas fa-check-circle text-4xl text-green-500 mb-4"></i>
                        <p class="text-gray-600">All payments are matched!</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Unpaid Invoices -->
        <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl border border-gray-200/50">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-bold text-gray-900 flex items-center">
                    <i class="fas fa-file-invoice text-red-500 mr-2"></i>
                    Unpaid Invoices ({{ $unpaidInvoices->count() }})
                </h3>
            </div>
            <div class="p-6 max-h-96 overflow-y-auto">
                @forelse($unpaidInvoices as $invoice)
                    <div class="mb-4 p-4 bg-gray-50 rounded-xl border border-gray-200">
                        <div class="flex items-center justify-between mb-2">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ $invoice->invoice_number }}</p>
                                <p class="text-xs text-gray-600">{{ $invoice->customer->name ?? 'N/A' }}</p>
                            </div>
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Unpaid</span>
                        </div>
                        <div class="text-xs text-gray-600 mb-2">
                            <p>Due: {{ $invoice->due_date->format('M d, Y') }}</p>
                            <p>Total: KES {{ number_format($invoice->total_amount, 2) }}</p>
                            <p class="font-semibold text-red-600">Balance: KES {{ number_format($invoice->balance, 2) }}</p>
                        </div>
                        <a href="{{ route('invoices.show', $invoice) }}" class="block w-full text-center bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:shadow-lg transition-all duration-200">
                            <i class="fas fa-eye mr-2"></i>View Invoice
                        </a>
                    </div>
                @empty
                    <div class="text-center py-12">
                        <i class="fas fa-check-circle text-4xl text-green-500 mb-4"></i>
                        <p class="text-gray-600">All invoices are paid!</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<!-- Match Modal -->
<div id="matchModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-xl p-8 max-w-md w-full mx-4">
        <h3 class="text-xl font-bold text-gray-900 mb-4">Match Payment to Invoice</h3>
        <form action="{{ route('reconciliation.match') }}" method="POST" id="matchForm">
            @csrf
            <input type="hidden" name="payment_id" id="modal_payment_id">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Select Invoice</label>
                <select name="invoice_id" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500">
                    <option value="">Select an invoice...</option>
                    @foreach($unpaidInvoices as $invoice)
                        <option value="{{ $invoice->id }}" data-balance="{{ $invoice->balance }}">
                            {{ $invoice->invoice_number }} - Balance: KES {{ number_format($invoice->balance, 2) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Amount to Match</label>
                <input type="number" name="amount" id="modal_amount" step="0.01" min="0.01" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500">
            </div>
            <div class="flex items-center justify-end space-x-4">
                <button type="button" onclick="closeMatchModal()" class="px-6 py-3 border border-gray-300 rounded-xl font-medium text-gray-700 hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" class="px-6 py-3 bg-gradient-to-r from-orange-600 to-red-600 text-white rounded-xl font-medium hover:shadow-lg transition-all duration-200">
                    <i class="fas fa-link mr-2"></i>Match
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showMatchModal(paymentId, paymentAmount) {
    document.getElementById('modal_payment_id').value = paymentId;
    document.getElementById('modal_amount').value = paymentAmount;
    document.getElementById('matchModal').classList.remove('hidden');
    
    // Update amount when invoice is selected
    document.querySelector('select[name="invoice_id"]').addEventListener('change', function() {
        const balance = parseFloat(this.options[this.selectedIndex].dataset.balance || 0);
        document.getElementById('modal_amount').value = Math.min(paymentAmount, balance);
    });
}

function closeMatchModal() {
    document.getElementById('matchModal').classList.add('hidden');
}

// Close modal on outside click
document.getElementById('matchModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeMatchModal();
    }
});
</script>
@endsection
