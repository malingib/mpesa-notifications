<?php $__env->startSection('title', 'Dashboard'); ?>

<?php $__env->startSection('content'); ?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- Modern Page Header -->
    <div class="mb-8 animate-fade-in">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-4xl font-bold text-gray-900 mb-2">Dashboard</h1>
                <p class="text-gray-600 flex items-center">
                    <i class="fas fa-user-circle mr-2 text-indigo-500"></i>
                    Welcome back, <span class="font-semibold text-indigo-600"><?php echo e(auth()->user()->name); ?></span>!
                </p>
            </div>
            <div class="hidden sm:flex items-center space-x-2 bg-white px-4 py-2 rounded-xl shadow-lg">
                <i class="fas fa-calendar-alt text-indigo-500"></i>
                <span class="text-sm font-medium text-gray-700"><?php echo e(now()->format('F d, Y')); ?></span>
            </div>
        </div>
    </div>

    <!-- Modern Stats Cards -->
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 mb-8">
        <!-- Today's Payments -->
        <div class="group relative bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl p-6 shadow-xl card-hover overflow-hidden animate-fade-in">
            <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16"></div>
            <div class="relative z-10">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-14 h-14 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center">
                        <i class="fas fa-credit-card text-white text-2xl"></i>
                    </div>
                    <span class="text-white/80 text-sm font-medium">Today</span>
                </div>
                <h3 class="text-white/80 text-sm font-medium mb-1">Payments</h3>
                <p class="text-3xl font-bold text-white mb-2"><?php echo e(number_format($todayPayments)); ?></p>
                <div class="flex items-center text-white/70 text-xs">
                    <i class="fas fa-arrow-up mr-1"></i>
                    <span>KES <?php echo e(number_format($todayAmount, 2)); ?></span>
                </div>
            </div>
        </div>

        <!-- Today's Amount -->
        <div class="group relative bg-gradient-to-br from-emerald-500 to-teal-600 rounded-2xl p-6 shadow-xl card-hover overflow-hidden animate-fade-in" style="animation-delay: 0.1s">
            <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16"></div>
            <div class="relative z-10">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-14 h-14 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center">
                        <i class="fas fa-money-bill-wave text-white text-2xl"></i>
                    </div>
                    <span class="text-white/80 text-sm font-medium">Today</span>
                </div>
                <h3 class="text-white/80 text-sm font-medium mb-1">Total Amount</h3>
                <p class="text-3xl font-bold text-white mb-2">KES <?php echo e(number_format($todayAmount, 2)); ?></p>
                <div class="flex items-center text-white/70 text-xs">
                    <i class="fas fa-chart-line mr-1"></i>
                    <span><?php echo e($todayPayments); ?> transactions</span>
                </div>
            </div>
        </div>

        <!-- SMS Sent Today -->
        <div class="group relative bg-gradient-to-br from-blue-500 to-cyan-600 rounded-2xl p-6 shadow-xl card-hover overflow-hidden animate-fade-in" style="animation-delay: 0.2s">
            <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16"></div>
            <div class="relative z-10">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-14 h-14 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center">
                        <i class="fas fa-sms text-white text-2xl"></i>
                    </div>
                    <span class="text-white/80 text-sm font-medium">Today</span>
                </div>
                <h3 class="text-white/80 text-sm font-medium mb-1">SMS Sent</h3>
                <p class="text-3xl font-bold text-white mb-2"><?php echo e(number_format($todaySmsSent)); ?></p>
                <div class="flex items-center text-white/70 text-xs">
                    <i class="fas fa-percentage mr-1"></i>
                    <span>
                        <?php if($todayPayments > 0): ?>
                            <?php echo e(number_format(($todaySmsSent / $todayPayments) * 100, 1)); ?>% 
                            (<?php echo e($todaySmsSent); ?>/<?php echo e($todayPayments); ?>)
                        <?php else: ?>
                            0% (0/0)
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- This Month -->
        <div class="group relative bg-gradient-to-br from-pink-500 to-rose-600 rounded-2xl p-6 shadow-xl card-hover overflow-hidden animate-fade-in" style="animation-delay: 0.3s">
            <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16"></div>
            <div class="relative z-10">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-14 h-14 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center">
                        <i class="fas fa-calendar-alt text-white text-2xl"></i>
                    </div>
                    <span class="text-white/80 text-sm font-medium"><?php echo e(now()->format('M Y')); ?></span>
                </div>
                <h3 class="text-white/80 text-sm font-medium mb-1">This Month</h3>
                <p class="text-3xl font-bold text-white mb-2"><?php echo e(number_format($monthPayments)); ?></p>
                <div class="flex items-center text-white/70 text-xs">
                    <i class="fas fa-coins mr-1"></i>
                    <span>KES <?php echo e(number_format($monthAmount, 2)); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Modern Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Payment Trends -->
        <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl p-6 border border-gray-200/50 animate-fade-in">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-gray-900 flex items-center">
                    <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center mr-3">
                        <i class="fas fa-chart-line text-white"></i>
                    </div>
                    Payment Trends
                </h3>
                <span class="text-sm text-gray-500">Last 7 Days</span>
            </div>
            <div style="height: 300px; position: relative;">
                <canvas id="paymentTrendsChart"></canvas>
            </div>
        </div>

        <!-- Status Distribution -->
        <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl p-6 border border-gray-200/50 animate-fade-in">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-gray-900 flex items-center">
                    <div class="w-10 h-10 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-xl flex items-center justify-center mr-3">
                        <i class="fas fa-chart-pie text-white"></i>
                    </div>
                    Status Distribution
                </h3>
            </div>
            <div style="height: 300px; position: relative;">
                <canvas id="statusChart"></canvas>
            </div>
        </div>
    </div>

    <!-- SMS Stats & Top Merchants -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- SMS Statistics -->
        <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl p-6 border border-gray-200/50 animate-fade-in">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-gray-900 flex items-center">
                    <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-cyan-600 rounded-xl flex items-center justify-center mr-3">
                        <i class="fas fa-sms text-white"></i>
                    </div>
                    SMS Statistics
                </h3>
                <span class="text-xs text-gray-500 bg-gray-100 px-3 py-1 rounded-full font-medium">All Time</span>
            </div>
            <div class="space-y-4">
                <div class="flex justify-between items-center p-4 bg-gradient-to-r from-gray-50 to-gray-100 rounded-xl">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-list text-indigo-600"></i>
                        </div>
                        <span class="text-gray-700 font-medium">Total Payments</span>
                    </div>
                    <span class="font-bold text-gray-900 text-lg"><?php echo e(number_format($smsStats->total ?? 0)); ?></span>
                </div>
                <div class="flex justify-between items-center p-4 bg-gradient-to-r from-green-50 to-emerald-50 rounded-xl">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-check-circle text-green-600"></i>
                        </div>
                        <span class="text-gray-700 font-medium">SMS Sent</span>
                    </div>
                    <span class="font-bold text-green-600 text-lg"><?php echo e(number_format($smsStats->sent ?? 0)); ?></span>
                </div>
                <div class="flex justify-between items-center p-4 bg-gradient-to-r from-red-50 to-pink-50 rounded-xl">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-times-circle text-red-600"></i>
                        </div>
                        <span class="text-gray-700 font-medium">Failed</span>
                    </div>
                    <span class="font-bold text-red-600 text-lg"><?php echo e(number_format($smsStats->failed ?? 0)); ?></span>
                </div>
                <?php if(($smsStats->pending ?? 0) > 0): ?>
                <div class="flex justify-between items-center p-4 bg-gradient-to-r from-yellow-50 to-amber-50 rounded-xl">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-clock text-yellow-600"></i>
                        </div>
                        <span class="text-gray-700 font-medium">Pending</span>
                    </div>
                    <span class="font-bold text-yellow-600 text-lg"><?php echo e(number_format($smsStats->pending ?? 0)); ?></span>
                </div>
                <?php endif; ?>
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <div class="flex justify-between items-center p-4 bg-gradient-to-r from-indigo-50 to-purple-50 rounded-xl">
                        <span class="text-gray-700 font-semibold flex items-center">
                            <i class="fas fa-percentage mr-2 text-indigo-600"></i>
                            Success Rate
                        </span>
                        <span class="font-bold text-indigo-600 text-2xl">
                            <?php echo e($smsStats->total > 0 ? number_format(($smsStats->sent / $smsStats->total) * 100, 1) : 0); ?>%
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Merchants -->
        <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl p-6 border border-gray-200/50 animate-fade-in">
            <h3 class="text-xl font-bold text-gray-900 mb-6 flex items-center">
                <div class="w-10 h-10 bg-gradient-to-br from-pink-500 to-rose-600 rounded-xl flex items-center justify-center mr-3">
                    <i class="fas fa-store text-white"></i>
                </div>
                Top Merchants
            </h3>
            <div class="space-y-3">
                <?php $__empty_1 = true; $__currentLoopData = $topMerchants; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $merchant): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <div class="flex justify-between items-center p-4 bg-gradient-to-r from-gray-50 to-gray-100 rounded-xl hover:from-indigo-50 hover:to-purple-50 transition-all duration-200">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-lg flex items-center justify-center text-white font-bold mr-3">
                                <?php echo e($index + 1); ?>

                            </div>
                            <div>
                                <p class="font-semibold text-gray-900"><?php echo e(strtoupper($merchant->account_type)); ?> - <?php echo e($merchant->account_number); ?></p>
                                <p class="text-sm text-gray-500"><?php echo e($merchant->payments_count); ?> payments</p>
                            </div>
                        </div>
                        <span class="bg-indigo-100 text-indigo-600 px-3 py-1 rounded-lg font-bold"><?php echo e($merchant->payments_count); ?></span>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-inbox text-4xl mb-2"></i>
                        <p>No merchants found</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Payments -->
    <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl border border-gray-200/50 overflow-hidden animate-fade-in">
        <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-gray-100">
            <h3 class="text-xl font-bold text-gray-900 flex items-center">
                <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center mr-3">
                    <i class="fas fa-history text-white"></i>
                </div>
                Recent Payments
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Transaction</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Amount</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Merchant</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">SMS</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Date</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php $__empty_1 = true; $__currentLoopData = $recentPayments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr class="hover:bg-indigo-50 transition-colors duration-150">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-receipt text-indigo-600 text-xs"></i>
                                    </div>
                                    <span class="text-sm font-mono text-gray-900"><?php echo e(substr($payment->transaction_id, 0, 20)); ?>...</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm font-bold text-gray-900">KES <?php echo e(number_format($payment->amount, 2)); ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo e($payment->merchant->account_number ?? 'N/A'); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php echo e($payment->status === 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'); ?>">
                                    <?php echo e(ucfirst($payment->status)); ?>

                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if($payment->sms_sent): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <i class="fas fa-check-circle mr-1"></i>Sent
                                    </span>
                                <?php elseif($payment->sms_retry_count > 0): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800" title="Failed: <?php echo e($payment->sms_error ?? 'Unknown error'); ?>">
                                        <i class="fas fa-times-circle mr-1"></i>Failed
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                        <i class="fas fa-clock mr-1"></i>Pending
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo e($payment->created_at->format('M d, Y H:i')); ?></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-inbox text-4xl mb-2"></i>
                                <p>No payments found</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php $__env->startSection('scripts'); ?>
<script>
    // Optimized Charts (lazy load on DOM ready)
    document.addEventListener('DOMContentLoaded', function() {
        // Payment Trends Chart - optimized
        const trendsCtx = document.getElementById('paymentTrendsChart');
        if (trendsCtx) {
            const trendsData = <?php echo json_encode($paymentTrends); ?>;
            
            new Chart(trendsCtx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: trendsData.map(d => d.date),
                    datasets: [{
                        label: 'Payments',
                        data: trendsData.map(d => d.count),
                        borderColor: 'rgb(99, 102, 241)',
                        backgroundColor: 'rgba(99, 102, 241, 0.05)',
                        tension: 0.4,
                        fill: true,
                        borderWidth: 2,
                        pointRadius: 3,
                        pointHoverRadius: 5
                    }, {
                        label: 'Amount (KES)',
                        data: trendsData.map(d => d.total),
                        borderColor: 'rgb(16, 185, 129)',
                        backgroundColor: 'rgba(16, 185, 129, 0.05)',
                        tension: 0.4,
                        fill: true,
                        borderWidth: 2,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        yAxisID: 'y1'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 750 },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: { usePointStyle: true, padding: 10, font: { size: 11, weight: '600' } }
                        },
                        tooltip: { enabled: true, backgroundColor: 'rgba(0, 0, 0, 0.8)', padding: 10 }
                    },
                    scales: {
                        y: { beginAtZero: true, grid: { color: 'rgba(0, 0, 0, 0.03)' }, ticks: { font: { size: 10 } } },
                        y1: { type: 'linear', display: true, position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, ticks: { font: { size: 10 } } },
                        x: { grid: { color: 'rgba(0, 0, 0, 0.03)' }, ticks: { font: { size: 10 }, maxRotation: 45 } }
                    }
                }
            });
        }

        // Status Distribution Chart - optimized
        const statusCtx = document.getElementById('statusChart');
        if (statusCtx) {
            const statusData = <?php echo json_encode($statusDistribution); ?>;
            
            new Chart(statusCtx.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: statusData.map(d => d.status),
                    datasets: [{
                        data: statusData.map(d => d.count),
                        backgroundColor: ['rgb(16, 185, 129)', 'rgb(251, 191, 36)', 'rgb(239, 68, 68)', 'rgb(99, 102, 241)'],
                        borderWidth: 0,
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 750 },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom',
                            labels: { usePointStyle: true, padding: 12, font: { size: 11, weight: '600' } }
                        },
                        tooltip: { enabled: true, backgroundColor: 'rgba(0, 0, 0, 0.8)', padding: 10 }
                    }
                }
            });
        }
    });
</script>
<?php $__env->stopSection(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/zumi/php/mpesa-notifications/resources/views/dashboard.blade.php ENDPATH**/ ?>