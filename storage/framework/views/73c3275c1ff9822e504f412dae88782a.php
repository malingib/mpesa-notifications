<?php $__env->startSection('title', 'My Payments'); ?>

<?php $__env->startSection('content'); ?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- Header -->
    <div class="mb-8 animate-fade-in">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h2 class="text-3xl font-bold text-gray-900 flex items-center">
                    <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center mr-4 shadow-lg">
                        <i class="fas fa-credit-card text-white text-xl"></i>
                    </div>
                    My Payments
                </h2>
                <p class="mt-2 text-gray-600">View, filter, and analyze your payment transactions</p>
            </div>
            <a href="<?php echo e(route('dashboard')); ?>" class="bg-gradient-to-r from-gray-500 to-gray-600 text-white px-6 py-3 rounded-xl font-medium hover:shadow-lg transition-all duration-200 transform hover:scale-105">
                <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Earnings Calculator -->
    <div class="bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl shadow-xl p-6 mb-8 animate-fade-in">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-white flex items-center">
                <i class="fas fa-calculator mr-3"></i>
                Earnings Calculator
            </h3>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <button onclick="calculateEarnings('daily')" class="earnings-btn bg-white/20 hover:bg-white/30 text-white px-4 py-3 rounded-xl font-medium transition-all duration-200 transform hover:scale-105">
                <i class="fas fa-calendar-day mr-2"></i>Today
            </button>
            <button onclick="calculateEarnings('monthly')" class="earnings-btn bg-white/20 hover:bg-white/30 text-white px-4 py-3 rounded-xl font-medium transition-all duration-200 transform hover:scale-105">
                <i class="fas fa-calendar-alt mr-2"></i>This Month
            </button>
            <button onclick="calculateEarnings('annual')" class="earnings-btn bg-white/20 hover:bg-white/30 text-white px-4 py-3 rounded-xl font-medium transition-all duration-200 transform hover:scale-105">
                <i class="fas fa-calendar mr-2"></i>This Year
            </button>
            <button onclick="calculateEarnings('all')" class="earnings-btn bg-white/20 hover:bg-white/30 text-white px-4 py-3 rounded-xl font-medium transition-all duration-200 transform hover:scale-105">
                <i class="fas fa-infinity mr-2"></i>All Time
            </button>
        </div>
        <div id="earningsResult" class="bg-white/10 backdrop-blur-sm rounded-xl p-6 hidden">
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <div>
                    <p class="text-white/80 text-sm mb-1">Period</p>
                    <p class="text-white font-bold text-lg" id="earningsPeriod">-</p>
                </div>
                <div>
                    <p class="text-white/80 text-sm mb-1">Total Earnings</p>
                    <p class="text-white font-bold text-2xl" id="earningsTotal">KES 0.00</p>
                </div>
                <div>
                    <p class="text-white/80 text-sm mb-1">Transactions</p>
                    <p class="text-white font-bold text-lg" id="earningsCount">0</p>
                </div>
                <div>
                    <p class="text-white/80 text-sm mb-1">Average</p>
                    <p class="text-white font-bold text-lg" id="earningsAverage">KES 0.00</p>
                </div>
                <div>
                    <p class="text-white/80 text-sm mb-1">Max / Min</p>
                    <p class="text-white font-bold text-sm" id="earningsRange">KES 0.00 / KES 0.00</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl p-6 border border-gray-200/50 animate-fade-in">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">Total Payments</p>
                    <p class="text-3xl font-bold text-gray-900"><?php echo e(number_format($totalPayments ?? 0)); ?></p>
                    <?php if($dateFrom || $dateTo): ?>
                        <p class="text-xs text-gray-500 mt-1">Filtered results</p>
                    <?php endif; ?>
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
                    <p class="text-3xl font-bold text-green-600">KES <?php echo e(number_format($totalAmount ?? 0, 2)); ?></p>
                    <?php if($dateFrom || $dateTo): ?>
                        <p class="text-xs text-gray-500 mt-1">Filtered results</p>
                    <?php endif; ?>
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
                    <p class="text-3xl font-bold text-blue-600"><?php echo e(number_format($smsSent ?? 0)); ?></p>
                    <?php if($dateFrom || $dateTo): ?>
                        <p class="text-xs text-gray-500 mt-1">Filtered results</p>
                    <?php endif; ?>
                </div>
                <div class="w-16 h-16 bg-blue-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-sms text-blue-600 text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters and View Mode Tabs -->
    <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl border border-gray-200/50 overflow-hidden mb-8 animate-fade-in">
        <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-gray-100">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <!-- View Mode Tabs -->
                <div class="flex items-center space-x-2 bg-gray-200 rounded-lg p-1">
                    <button onclick="switchView('list')" class="view-tab px-4 py-2 rounded-md text-sm font-medium transition-all <?php echo e($viewMode === 'list' ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-600 hover:text-gray-900'); ?>">
                        <i class="fas fa-list mr-2"></i>List View
                    </button>
                    <button onclick="switchView('top_days')" class="view-tab px-4 py-2 rounded-md text-sm font-medium transition-all <?php echo e($viewMode === 'top_days' ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-600 hover:text-gray-900'); ?>">
                        <i class="fas fa-calendar-day mr-2"></i>Top Days
                    </button>
                    <button onclick="switchView('top_months')" class="view-tab px-4 py-2 rounded-md text-sm font-medium transition-all <?php echo e($viewMode === 'top_months' ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-600 hover:text-gray-900'); ?>">
                        <i class="fas fa-calendar-alt mr-2"></i>Top Months
                    </button>
                </div>

                <!-- Date Filters -->
                <form method="GET" action="<?php echo e(route('payments.index')); ?>" class="flex items-center space-x-3 flex-wrap">
                    <input type="hidden" name="view" value="<?php echo e($viewMode); ?>">
                    <div class="flex items-center space-x-2">
                        <label class="text-sm font-medium text-gray-700">From:</label>
                        <input 
                            type="date" 
                            name="date_from" 
                            value="<?php echo e($dateFrom); ?>"
                            class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-sm"
                        >
                    </div>
                    <div class="flex items-center space-x-2">
                        <label class="text-sm font-medium text-gray-700">To:</label>
                        <input 
                            type="date" 
                            name="date_to" 
                            value="<?php echo e($dateTo); ?>"
                            class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-sm"
                        >
                    </div>
                    <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors text-sm font-medium">
                        <i class="fas fa-filter mr-1"></i>Apply Filters
                    </button>
                    <?php if($dateFrom || $dateTo): ?>
                        <a href="<?php echo e(route('payments.index', ['view' => $viewMode])); ?>" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition-colors text-sm font-medium">
                            <i class="fas fa-times mr-1"></i>Clear
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Content Area -->
        <?php if($viewMode === 'list'): ?>
            <!-- Payments List View -->
            <!-- Search and Filter Bar -->
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <div class="flex items-center space-x-3 flex-wrap">
                    <input 
                        type="text" 
                        id="searchInput" 
                        placeholder="Search by transaction ID, payer name, merchant..." 
                        class="flex-1 min-w-[200px] px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-sm"
                    >
                    <select 
                        id="statusFilter" 
                        class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-sm"
                    >
                        <option value="">All Status</option>
                        <option value="completed">Completed</option>
                        <option value="pending">Pending</option>
                        <option value="failed">Failed</option>
                    </select>
                    <select 
                        id="smsFilter" 
                        class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-sm"
                    >
                        <option value="">All SMS Status</option>
                        <option value="sent">SMS Sent</option>
                        <option value="pending">SMS Pending</option>
                        <option value="failed">SMS Failed</option>
                    </select>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">Merchant</th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">Transaction ID</th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">Payer</th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">Amount</th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">Status</th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">SMS</th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">Date</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200" id="paymentsTableBody">
                        <?php $__empty_1 = true; $__currentLoopData = $payments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr class="hover:bg-indigo-50 transition-colors duration-150 payment-row" data-status="<?php echo e(strtolower($payment->status)); ?>">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-500 rounded-full flex items-center justify-center mr-3 text-white font-semibold text-xs">
                                            <?php echo e(strtoupper(substr($payment->merchant->account_number ?? 'N', 0, 1))); ?>

                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900"><?php echo e($payment->merchant->account_number ?? 'N/A'); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo e(ucfirst($payment->merchant->account_type ?? 'N/A')); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-mono text-gray-900"><?php echo e(substr($payment->transaction_id, 0, 20)); ?>...</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo e($payment->payer_name ?? 'N/A'); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo e($payment->phone_number ?? ''); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-bold text-gray-900">KES <?php echo e(number_format($payment->amount, 2)); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if($payment->status === 'completed'): ?>
                                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            <i class="fas fa-check-circle mr-1"></i>Completed
                                        </span>
                                    <?php elseif($payment->status === 'pending'): ?>
                                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                            <i class="fas fa-clock mr-1"></i>Pending
                                        </span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                            <i class="fas fa-times-circle mr-1"></i>Failed
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if($payment->sms_sent): ?>
                                        <span class="sms-status inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            <i class="fas fa-check-circle mr-1"></i>Sent
                                        </span>
                                    <?php elseif($payment->sms_retry_count > 0): ?>
                                        <span class="sms-status inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800" title="Failed: <?php echo e($payment->sms_error ?? 'Unknown error'); ?>">
                                            <i class="fas fa-times-circle mr-1"></i>Failed
                                        </span>
                                    <?php else: ?>
                                        <span class="sms-status inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                            <i class="fas fa-clock mr-1"></i>Pending
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo e($payment->created_at->format('M d, Y H:i')); ?>

                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-inbox text-4xl mb-2"></i>
                                    <p>No payments found</p>
                                    <?php if($dateFrom || $dateTo): ?>
                                        <p class="text-sm mt-2">Try adjusting your date filters</p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if($payments && $payments->hasPages()): ?>
                <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                    <?php echo e($payments->links()); ?>

                </div>
            <?php endif; ?>

        <?php elseif($viewMode === 'top_days'): ?>
            <!-- Top Revenue Days View -->
            <div class="p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-trophy text-yellow-500 mr-2"></i>
                    Top Revenue Days
                </h3>
                <div class="space-y-3">
                    <?php $__empty_1 = true; $__currentLoopData = $topRevenueData; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $day): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <div class="flex items-center justify-between p-4 bg-gradient-to-r from-gray-50 to-gray-100 rounded-xl hover:from-indigo-50 hover:to-purple-50 transition-all duration-200">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-gradient-to-br from-yellow-400 to-orange-500 rounded-lg flex items-center justify-center text-white font-bold mr-4">
                                    <?php echo e($index + 1); ?>

                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900"><?php echo e($day['date']); ?></p>
                                    <p class="text-sm text-gray-500"><?php echo e(number_format($day['count'])); ?> transaction<?php echo e($day['count'] != 1 ? 's' : ''); ?></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-green-600 text-lg">KES <?php echo e(number_format($day['total'], 2)); ?></p>
                                <p class="text-xs text-gray-500">Revenue</p>
                            </div>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <div class="text-center py-12 text-gray-500">
                            <i class="fas fa-chart-line text-4xl mb-2"></i>
                            <p>No revenue data available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif($viewMode === 'top_months'): ?>
            <!-- Top Revenue Months View -->
            <div class="p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-trophy text-yellow-500 mr-2"></i>
                    Top Revenue Months
                </h3>
                <div class="space-y-3">
                    <?php $__empty_1 = true; $__currentLoopData = $topRevenueData; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $month): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <div class="flex items-center justify-between p-4 bg-gradient-to-r from-gray-50 to-gray-100 rounded-xl hover:from-indigo-50 hover:to-purple-50 transition-all duration-200">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-gradient-to-br from-purple-400 to-pink-500 rounded-lg flex items-center justify-center text-white font-bold mr-4">
                                    <?php echo e($index + 1); ?>

                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900"><?php echo e($month['label']); ?></p>
                                    <p class="text-sm text-gray-500"><?php echo e(number_format($month['count'])); ?> transaction<?php echo e($month['count'] != 1 ? 's' : ''); ?></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-green-600 text-lg">KES <?php echo e(number_format($month['total'], 2)); ?></p>
                                <p class="text-xs text-gray-500">Revenue</p>
                            </div>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <div class="text-center py-12 text-gray-500">
                            <i class="fas fa-chart-line text-4xl mb-2"></i>
                            <p>No revenue data available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('scripts'); ?>
<script>
    // Switch view mode
    function switchView(mode) {
        const url = new URL(window.location.href);
        url.searchParams.set('view', mode);
        // Preserve date filters
        <?php if($dateFrom): ?>
            url.searchParams.set('date_from', '<?php echo e($dateFrom); ?>');
        <?php endif; ?>
        <?php if($dateTo): ?>
            url.searchParams.set('date_to', '<?php echo e($dateTo); ?>');
        <?php endif; ?>
        window.location.href = url.toString();
    }

    // Calculate earnings
    function calculateEarnings(period) {
        const buttons = document.querySelectorAll('.earnings-btn');
        buttons.forEach(btn => btn.disabled = true);
        
        const resultDiv = document.getElementById('earningsResult');
        if (!resultDiv) {
            console.error('Earnings result div not found');
            buttons.forEach(btn => btn.disabled = false);
            return;
        }
        
        resultDiv.classList.remove('hidden');
        
        // Show loading state without removing the structure
        const periodEl = document.getElementById('earningsPeriod');
        const totalEl = document.getElementById('earningsTotal');
        const countEl = document.getElementById('earningsCount');
        const averageEl = document.getElementById('earningsAverage');
        const rangeEl = document.getElementById('earningsRange');
        
        if (periodEl) periodEl.textContent = 'Calculating...';
        if (totalEl) totalEl.textContent = 'KES 0.00';
        if (countEl) countEl.textContent = '0';
        if (averageEl) averageEl.textContent = 'KES 0.00';
        if (rangeEl) rangeEl.textContent = 'KES 0.00 / KES 0.00';

        fetch('<?php echo e(route("payments.calculate-earnings")); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ period: period }),
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            buttons.forEach(btn => btn.disabled = false);
            
            if (data.success) {
                // Update elements safely
                if (periodEl) periodEl.textContent = data.label;
                if (totalEl) totalEl.textContent = data.formatted_total;
                if (countEl) countEl.textContent = data.count.toLocaleString();
                if (averageEl) averageEl.textContent = data.formatted_average;
                if (rangeEl) rangeEl.textContent = data.formatted_max + ' / ' + data.formatted_min;
            } else {
                if (totalEl) totalEl.textContent = 'Error calculating earnings';
                if (periodEl) periodEl.textContent = 'Error';
            }
        })
        .catch(error => {
            buttons.forEach(btn => btn.disabled = false);
            console.error('Error calculating earnings:', error);
            if (totalEl) totalEl.textContent = 'Error: ' + error.message;
            if (periodEl) periodEl.textContent = 'Error';
        });
    }

    // Client-side search and filter (for list view)
    document.addEventListener('DOMContentLoaded', function() {
        <?php if($viewMode === 'list'): ?>
        const searchInput = document.getElementById('searchInput');
        const statusFilter = document.getElementById('statusFilter');
        const smsFilter = document.getElementById('smsFilter');
        const tableBody = document.getElementById('paymentsTableBody');
        
        if (!tableBody) {
            console.warn('Payments table body not found');
            return;
        }
        
        let rows = tableBody.querySelectorAll('.payment-row');

        function filterPayments() {
            // Re-query rows in case DOM changed
            rows = tableBody.querySelectorAll('.payment-row');
            
            if (!rows || rows.length === 0) {
                return;
            }
            
            const searchTerm = (searchInput && searchInput.value ? searchInput.value : '').toLowerCase();
            const selectedStatus = (statusFilter && statusFilter.value ? statusFilter.value : '').toLowerCase();
            const selectedSmsStatus = (smsFilter && smsFilter.value ? smsFilter.value : '').toLowerCase();

            rows.forEach(function(row) {
                if (!row) return;
                
                try {
                    const text = (row.textContent || row.innerText || '').toLowerCase();
                    const status = (row.getAttribute('data-status') || '').toLowerCase();
                    
                    // Check SMS status from row content or data attribute
                    const smsStatus = row.querySelector('.sms-status') ? 
                        (row.querySelector('.sms-status').textContent || '').toLowerCase() : '';
                    
                    const matchesSearch = !searchTerm || text.includes(searchTerm);
                    const matchesStatus = !selectedStatus || status === selectedStatus.toLowerCase();
                    const matchesSmsStatus = !selectedSmsStatus || smsStatus.includes(selectedSmsStatus);

                    if (row.style) {
                        row.style.display = (matchesSearch && matchesStatus && matchesSmsStatus) ? '' : 'none';
                    }
                } catch (e) {
                    console.error('Error filtering row:', e);
                }
            });
        }

        if (searchInput) {
            searchInput.addEventListener('input', filterPayments);
        }
        if (statusFilter) {
            statusFilter.addEventListener('change', filterPayments);
        }
        if (smsFilter) {
            smsFilter.addEventListener('change', filterPayments);
        }
        <?php endif; ?>
    });
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/zumi/php/mpesa-notifications/resources/views/payments/index.blade.php ENDPATH**/ ?>