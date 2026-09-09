@extends('layouts.app')

@section('title', 'Payments Management')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- Header -->
    <div class="mb-8 animate-fade-in">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-3xl font-bold text-gray-900 flex items-center">
                    <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center mr-4 shadow-lg">
                        <i class="fas fa-credit-card text-white text-xl"></i>
                    </div>
                    Payments Management
                </h2>
                <p class="mt-2 text-gray-600">View and manage all payment transactions</p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="bg-gradient-to-r from-gray-500 to-gray-600 text-white px-6 py-3 rounded-xl font-medium hover:shadow-lg transition-all duration-200 transform hover:scale-105">
                <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl p-6 border border-gray-200/50 animate-fade-in">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">Total Payments</p>
                    <p class="text-3xl font-bold text-gray-900">{{ number_format($totalPayments ?? 0) }}</p>
                </div>
                <div class="w-16 h-16 bg-indigo-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-list text-indigo-600 text-2xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl p-6 border border-gray-200/50 animate-fade-in" style="animation-delay: 0.1s">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">Total Amount</p>
                    <p class="text-3xl font-bold text-green-600">KES {{ number_format($totalAmount ?? 0, 2) }}</p>
                </div>
                <div class="w-16 h-16 bg-green-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-money-bill-wave text-green-600 text-2xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl p-6 border border-gray-200/50 animate-fade-in" style="animation-delay: 0.2s">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">SMS Sent</p>
                    <p class="text-3xl font-bold text-blue-600">{{ number_format($smsSent ?? 0) }}</p>
                </div>
                <div class="w-16 h-16 bg-blue-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-sms text-blue-600 text-2xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl p-6 border border-gray-200/50 animate-fade-in" style="animation-delay: 0.3s">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">Success Rate</p>
                    <p class="text-3xl font-bold text-purple-600">{{ $totalPayments > 0 ? number_format(($smsSent / $totalPayments) * 100, 1) : 0 }}%</p>
                </div>
                <div class="w-16 h-16 bg-purple-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-percentage text-purple-600 text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Payments Table -->
    <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl border border-gray-200/50 overflow-hidden animate-fade-in">
        <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-gray-100">
            <div class="flex items-center justify-between">
                <h3 class="text-xl font-bold text-gray-900 flex items-center">
                    <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center mr-3">
                        <i class="fas fa-list text-white"></i>
                    </div>
                    All Payments
                </h3>
                <div class="flex items-center space-x-3">
                    <input type="text" id="searchInput" placeholder="Search payments..." class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    <select id="statusFilter" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        <option value="">All Status</option>
                        <option value="completed">Completed</option>
                        <option value="pending">Pending</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">User</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">Transaction ID</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">Amount</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">Status</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">SMS</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">Date</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200" id="paymentsTableBody">
                    @forelse($payments as $payment)
                        <tr class="hover:bg-indigo-50 transition-colors duration-150 payment-row" data-status="{{ strtolower($payment->status) }}">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-500 rounded-full flex items-center justify-center mr-3 text-white font-semibold text-xs">
                                        {{ strtoupper(substr($payment->user->name ?? 'N', 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">{{ $payment->user->name ?? 'N/A' }}</div>
                                        <div class="text-xs text-gray-500">{{ $payment->merchant->account_number ?? 'N/A' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-mono text-gray-900">{{ $payment->transaction_id }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-bold text-gray-900">KES {{ number_format($payment->amount, 2) }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($payment->status === 'completed')
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        <i class="fas fa-check-circle mr-1"></i>Completed
                                    </span>
                                @elseif($payment->status === 'pending')
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                        <i class="fas fa-clock mr-1"></i>Pending
                                    </span>
                                @else
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                        <i class="fas fa-times-circle mr-1"></i>Failed
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($payment->sms_sent)
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        <i class="fas fa-check mr-1"></i>Sent
                                    </span>
                                @else
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                        <i class="fas fa-times mr-1"></i>Not Sent
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $payment->created_at->format('M d, Y H:i') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex items-center space-x-2">
                                    <a href="#" class="text-indigo-600 hover:text-indigo-900" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    @if(!$payment->sms_sent)
                                        <a href="#" class="text-green-600 hover:text-green-900" title="Resend SMS">
                                            <i class="fas fa-paper-plane"></i>
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-inbox text-4xl mb-2"></i>
                                <p>No payments found</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($payments->hasPages())
            <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                {{ $payments->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

@section('scripts')
<script>
    // Search and filter functionality
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const statusFilter = document.getElementById('statusFilter');
        const tableBody = document.getElementById('paymentsTableBody');
        const rows = tableBody.querySelectorAll('.payment-row');

        function filterPayments() {
            const searchTerm = searchInput.value.toLowerCase();
            const selectedStatus = statusFilter.value;

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const status = row.getAttribute('data-status');
                
                const matchesSearch = text.includes(searchTerm);
                const matchesStatus = !selectedStatus || status === selectedStatus.toLowerCase();

                row.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
            });
        }

        searchInput.addEventListener('input', filterPayments);
        statusFilter.addEventListener('change', filterPayments);
    });
</script>
@endsection
