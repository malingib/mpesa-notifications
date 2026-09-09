<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Merchant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class OptimizedDashboardController extends Controller
{
    /**
     * User dashboard (optimized)
     * 
     * Uses caching, optimized queries, and limited data points.
     */
    public function index()
    {
        $user = Auth::user();
        
        // Cache key with user ID and 5-minute TTL
        $cacheKey = "user_dashboard_{$user->id}_" . now()->format('Y-m-d-H-i');
        
        $data = Cache::remember($cacheKey, 300, function () use ($user) {
            return $this->getDashboardData($user);
        });

        return view('dashboard', $data);
    }

    /**
     * Get dashboard data (optimized queries)
     */
    private function getDashboardData($user): array
    {
        // Today's stats (single query)
        $todayStats = Payment::where('user_id', $user->id)
            ->whereDate('created_at', today())
            ->selectRaw('
                COUNT(*) as payments,
                COALESCE(SUM(amount), 0) as amount,
                SUM(CASE WHEN sms_sent = 1 THEN 1 ELSE 0 END) as sms_sent
            ')
            ->first();

        // This month's stats (single query)
        $monthStats = Payment::where('user_id', $user->id)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->selectRaw('
                COUNT(*) as payments,
                COALESCE(SUM(amount), 0) as amount
            ')
            ->first();

        // Payment trends (last 7 days, daily aggregates only)
        $paymentTrends = Payment::where('user_id', $user->id)
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
        $statusDistribution = Payment::where('user_id', $user->id)
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
        $smsStats = Payment::where('user_id', $user->id)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN sms_sent = 1 THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN sms_sent = 0 AND sms_retry_count > 0 THEN 1 ELSE 0 END) as failed
            ')
            ->first();

        // Top merchants (limited to 5, optimized)
        $topMerchants = Merchant::where('user_id', $user->id)
            ->select('id', 'account_type', 'account_number')
            ->withCount(['payments' => function ($query) {
                $query->where('created_at', '>=', now()->subDays(30));
            }])
            ->orderBy('payments_count', 'desc')
            ->limit(5)
            ->get();

        // Recent payments (limited to 10, select only needed columns)
        $recentPayments = Payment::where('user_id', $user->id)
            ->select('id', 'merchant_id', 'transaction_id', 'amount', 'status', 'sms_sent', 'created_at')
            ->with('merchant:id,account_number')
            ->latest()
            ->limit(10)
            ->get();

        return [
            'todayPayments' => (int) ($todayStats->payments ?? 0),
            'todayAmount' => (float) ($todayStats->amount ?? 0),
            'todaySmsSent' => (int) ($todayStats->sms_sent ?? 0),
            'monthPayments' => (int) ($monthStats->payments ?? 0),
            'monthAmount' => (float) ($monthStats->amount ?? 0),
            'paymentTrends' => $paymentTrends,
            'statusDistribution' => $statusDistribution,
            'smsStats' => $smsStats,
            'topMerchants' => $topMerchants,
            'recentPayments' => $recentPayments,
        ];
    }
}
