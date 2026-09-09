@extends('layouts.app')

@section('title', 'Financial Dashboard')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- Header -->
    <div class="mb-8 animate-fade-in">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h2 class="text-3xl font-bold text-gray-900 flex items-center">
                    <div class="w-12 h-12 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-xl flex items-center justify-center mr-4 shadow-lg">
                        <i class="fas fa-chart-line text-white text-xl"></i>
                    </div>
                    Financial Dashboard
                </h2>
                <p class="mt-2 text-gray-600">Track your revenue, expenses, and profitability</p>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('dashboard') }}" class="bg-gradient-to-r from-gray-500 to-gray-600 text-white px-6 py-3 rounded-xl font-medium hover:shadow-lg transition-all duration-200">
                    <i class="fas fa-arrow-left mr-2"></i>Back
                </a>
            </div>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl p-6 mb-8 border border-gray-200/50">
        <form method="GET" action="{{ route('financial.dashboard') }}" class="flex items-end space-x-4">
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                <input type="date" name="start_date" value="{{ $startDate->format('Y-m-d') }}" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500">
            </div>
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                <input type="date" name="end_date" value="{{ $endDate->format('Y-m-d') }}" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500">
            </div>
            <div>
                <button type="submit" class="bg-gradient-to-r from-emerald-600 to-teal-600 text-white px-6 py-2 rounded-xl font-medium hover:shadow-lg transition-all duration-200">
                    <i class="fas fa-filter mr-2"></i>Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Key Metrics -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl p-6 shadow-xl text-white">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center">
                    <i class="fas fa-money-bill-wave text-2xl"></i>
                </div>
            </div>
            <p class="text-white/80 text-sm mb-1">Total Revenue</p>
            <p class="text-3xl font-bold">KES {{ number_format($metrics['revenue'] ?? 0, 2) }}</p>
        </div>
        <div class="bg-gradient-to-br from-red-500 to-pink-600 rounded-2xl p-6 shadow-xl text-white">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center">
                    <i class="fas fa-arrow-down text-2xl"></i>
                </div>
            </div>
            <p class="text-white/80 text-sm mb-1">Total Expenses</p>
            <p class="text-3xl font-bold">KES {{ number_format($metrics['expenses'] ?? 0, 2) }}</p>
        </div>
        <div class="bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl p-6 shadow-xl text-white">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center">
                    <i class="fas fa-chart-line text-2xl"></i>
                </div>
            </div>
            <p class="text-white/80 text-sm mb-1">Net Profit</p>
            <p class="text-3xl font-bold">KES {{ number_format($metrics['net_profit'] ?? 0, 2) }}</p>
        </div>
        <div class="bg-gradient-to-br from-purple-500 to-pink-600 rounded-2xl p-6 shadow-xl text-white">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center">
                    <i class="fas fa-percent text-2xl"></i>
                </div>
            </div>
            <p class="text-white/80 text-sm mb-1">Profit Margin</p>
            <p class="text-3xl font-bold">{{ number_format($metrics['profit_margin'] ?? 0, 1) }}%</p>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <!-- Revenue Trend -->
        <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl border border-gray-200/50 p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Revenue Trend</h3>
            <canvas id="revenueChart" height="300"></canvas>
        </div>

        <!-- Cash Flow -->
        <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl border border-gray-200/50 p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Cash Flow</h3>
            <div class="space-y-4">
                <div class="flex justify-between items-center p-4 bg-green-50 rounded-xl">
                    <span class="text-gray-700">Cash Inflows</span>
                    <span class="text-lg font-bold text-green-600">KES {{ number_format($cashFlow['operating_activities']['cash_inflows'] ?? 0, 2) }}</span>
                </div>
                <div class="flex justify-between items-center p-4 bg-red-50 rounded-xl">
                    <span class="text-gray-700">Cash Outflows</span>
                    <span class="text-lg font-bold text-red-600">KES {{ number_format($cashFlow['operating_activities']['cash_outflows'] ?? 0, 2) }}</span>
                </div>
                <div class="flex justify-between items-center p-4 bg-blue-50 rounded-xl border-2 border-blue-200">
                    <span class="text-gray-900 font-semibold">Net Cash Flow</span>
                    <span class="text-xl font-bold {{ ($cashFlow['operating_activities']['net_cash_flow'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        KES {{ number_format($cashFlow['operating_activities']['net_cash_flow'] ?? 0, 2) }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Reports -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <a href="{{ route('financial.profit-loss', ['start_date' => $startDate->format('Y-m-d'), 'end_date' => $endDate->format('Y-m-d')]) }}" class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl border border-gray-200/50 p-6 hover:shadow-2xl transition-all duration-200">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-indigo-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-file-invoice-dollar text-indigo-600 text-xl"></i>
                </div>
                <i class="fas fa-arrow-right text-gray-400"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-900 mb-2">Profit & Loss</h3>
            <p class="text-sm text-gray-600">View detailed P&L statement</p>
        </a>
        <a href="{{ route('financial.cash-flow', ['start_date' => $startDate->format('Y-m-d'), 'end_date' => $endDate->format('Y-m-d')]) }}" class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl border border-gray-200/50 p-6 hover:shadow-2xl transition-all duration-200">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-exchange-alt text-green-600 text-xl"></i>
                </div>
                <i class="fas fa-arrow-right text-gray-400"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-900 mb-2">Cash Flow</h3>
            <p class="text-sm text-gray-600">View cash flow statement</p>
        </a>
        <a href="{{ route('financial.tax-report', ['year' => $startDate->year]) }}" class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl border border-gray-200/50 p-6 hover:shadow-2xl transition-all duration-200">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-receipt text-purple-600 text-xl"></i>
                </div>
                <i class="fas fa-arrow-right text-gray-400"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-900 mb-2">Tax Report</h3>
            <p class="text-sm text-gray-600">View tax reports and VAT</p>
        </a>
    </div>
</div>

<script>
// Revenue Trend Chart
const revenueData = @json($revenueTrends);
const ctx = document.getElementById('revenueChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: revenueData.map(item => item.date),
        datasets: [{
            label: 'Revenue',
            data: revenueData.map(item => item.revenue),
            borderColor: 'rgb(16, 185, 129)',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return 'KES ' + value.toLocaleString();
                    }
                }
            }
        }
    }
});
</script>
@endsection
