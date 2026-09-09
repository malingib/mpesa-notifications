<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\User;
use App\Models\Merchant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class OptimizedAdminDashboardController extends Controller
{
    /**
     * Admin dashboard (optimized)
     * 
     * Uses caching, optimized queries, and limited data points for charts.
     */
    public function index()
    {
        // Cache key with 5-minute TTL
        $cacheKey = 'admin_dashboard_' . now()->format('Y-m-d-H-i');
        
        $data = Cache::remember($cacheKey, 300, function () {
            return $this->getDashboardData();
        });

        return view('admin.dashboard', $data);
    }

    /**
     * Get dashboard data (optimized queries)
     */
    private function getDashboardData(): array
    {
        // System-wide stats (single query with aggregation)
        $userStats = User::withoutGlobalScopes()
            ->selectRaw('
                COUNT(*) as total_users,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users
            ')
            ->where('role', 'client')
            ->first();

        $merchantStats = Merchant::withoutGlobalScopes()
            ->selectRaw('
                COUNT(*) as total_merchants,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_merchants
            ')
            ->first();

        // Today's stats (single query)
        $todayStats = Payment::withoutGlobalScopes()
            ->whereDate('created_at', today())
            ->selectRaw('
                COUNT(*) as payments,
                COALESCE(SUM(amount), 0) as amount,
                SUM(CASE WHEN sms_sent = 1 THEN 1 ELSE 0 END) as sms_sent
            ')
            ->first();

        // This month's stats (single query)
        $monthStats = Payment::withoutGlobalScopes()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->selectRaw('
                COUNT(*) as payments,
                COALESCE(SUM(amount), 0) as amount
            ')
            ->first();

        // Payment trends (last 7 days, limited to daily aggregates)
        $paymentTrends = Payment::withoutGlobalScopes()
            ->where('created_at', '>=', now()->subDays(7))
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('COALESCE(SUM(amount), 0) as total')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => \Carbon\Carbon::parse($item->date)->format('M d'),
                    'count' => (int) $item->count,
                    'total' => (float) $item->total,
                ];
            });

        // Payment status distribution (single query)
        $statusDistribution = Payment::withoutGlobalScopes()
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->map(function ($item) {
                return [
                    'status' => ucfirst($item->status),
                    'count' => (int) $item->count,
                ];
            });

        // SMS statistics (single query)
        $smsStats = Payment::withoutGlobalScopes()
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN sms_sent = 1 THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN sms_sent = 0 AND sms_retry_count > 0 THEN 1 ELSE 0 END) as failed
            ')
            ->first();

        // Top users (optimized with limit)
        $topUsers = User::withoutGlobalScopes()
            ->where('role', 'client')
            ->select('id', 'name', 'email')
            ->withCount(['payments' => function ($query) {
                $query->withoutGlobalScopes()
                    ->where('created_at', '>=', now()->subDays(30));
            }])
            ->orderBy('payments_count', 'desc')
            ->limit(10)
            ->get();

        // Recent payments (limited to 10, select only needed columns)
        $recentPayments = Payment::withoutGlobalScopes()
            ->select('id', 'user_id', 'merchant_id', 'transaction_id', 'amount', 'status', 'sms_sent', 'created_at')
            ->with(['user:id,name', 'merchant:id,account_number'])
            ->latest()
            ->limit(10)
            ->get();

        // Queue stats
        $queueStats = [
            'sms_queue' => $this->getQueueSize('sms-normal'),
            'sms_high_queue' => $this->getQueueSize('sms-high'),
            'audit_queue' => $this->getQueueSize('audit'),
        ];

        return [
            'totalUsers' => (int) ($userStats->total_users ?? 0),
            'activeUsers' => (int) ($userStats->active_users ?? 0),
            'totalMerchants' => (int) ($merchantStats->total_merchants ?? 0),
            'activeMerchants' => (int) ($merchantStats->active_merchants ?? 0),
            'todayPayments' => (int) ($todayStats->payments ?? 0),
            'todayAmount' => (float) ($todayStats->amount ?? 0),
            'todaySmsSent' => (int) ($todayStats->sms_sent ?? 0),
            'monthPayments' => (int) ($monthStats->payments ?? 0),
            'monthAmount' => (float) ($monthStats->amount ?? 0),
            'paymentTrends' => $paymentTrends,
            'statusDistribution' => $statusDistribution,
            'smsStats' => $smsStats,
            'topUsers' => $topUsers,
            'recentPayments' => $recentPayments,
            'queueStats' => $queueStats,
        ];
    }

    /**
     * Get queue size (optimized)
     */
    private function getQueueSize(string $queue): int
    {
        if (config('queue.default') !== 'redis') {
            return 0;
        }

        if (!class_exists('Illuminate\\Support\\Facades\\Redis', false)) {
            return 0;
        }

        try {
            $redisFacade = 'Illuminate\\Support\\Facades\\Redis';
            $redis = forward_static_call([$redisFacade, 'connection']);
            return (int) $redis->llen("queues:{$queue}");
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
