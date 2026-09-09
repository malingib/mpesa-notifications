<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\User;
use App\Models\Merchant;
use App\Models\SmsAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AdminDashboardController extends Controller
{
    /**
     * Admin dashboard
     * 
     * Note: Middleware is applied in routes/web.php
     */
    public function index()
    {
        // Cache dashboard data for 5 minutes
        $cacheKey = 'admin_dashboard_' . now()->format('Y-m-d-H-i');
        
        $data = \Illuminate\Support\Facades\Cache::remember($cacheKey, 300, function () {
            return $this->getDashboardData();
        });

        return view('admin.dashboard', $data);
    }

    /**
     * Get optimized dashboard data
     */
    private function getDashboardData(): array
    {
        // System-wide stats (single optimized query)
        $userStats = User::withoutGlobalScopes()
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active')
            ->where('role', 'client')
            ->first();

        $merchantStats = Merchant::withoutGlobalScopes()
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active')
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
            ->selectRaw('COUNT(*) as payments, COALESCE(SUM(amount), 0) as amount')
            ->first();

        // Payment trends (last 7 days, optimized - only daily aggregates)
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

        // Payment status distribution (optimized)
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

        // Top users (optimized - select only needed columns, limit 10)
        $topUsers = User::withoutGlobalScopes()
            ->where('role', 'client')
            ->select('id', 'name', 'email')
            ->withCount(['payments' => function ($query) {
                $query->withoutGlobalScopes()->where('created_at', '>=', now()->subDays(30));
            }])
            ->orderBy('payments_count', 'desc')
            ->limit(10)
            ->get();

        // Recent payments (optimized - select only needed columns, limit 10)
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
            'totalUsers' => (int) ($userStats->total ?? 0),
            'activeUsers' => (int) ($userStats->active ?? 0),
            'totalMerchants' => (int) ($merchantStats->total ?? 0),
            'activeMerchants' => (int) ($merchantStats->active ?? 0),
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
     * Get queue size
     * 
     * Returns queue size if Redis is available, otherwise returns 0.
     * Gracefully handles when Redis is not installed or configured.
     */
    private function getQueueSize(string $queue): int
    {
        // Check if queue connection is Redis
        if (config('queue.default') !== 'redis') {
            return 0;
        }
        
        // Check if Redis facade class exists (without autoloading)
        if (!class_exists('Illuminate\\Support\\Facades\\Redis', false)) {
            return 0;
        }
        
        try {
            // Dynamically call Redis facade to avoid class loading issues
            $redisFacade = 'Illuminate\\Support\\Facades\\Redis';
            $redis = forward_static_call([$redisFacade, 'connection']);
            
            // Get queue length
            return (int) $redis->llen("queues:{$queue}");
        } catch (\Throwable $e) {
            // Redis not available, not configured, or connection failed
            return 0;
        }
    }
}
