<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Merchant;
use App\Models\SmsAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * User dashboard
     * 
     * Note: Middleware is applied in routes/web.php
     */
    public function index()
    {
        $user = Auth::user();
        
        // Cache dashboard data for 5 minutes
        $cacheKey = "user_dashboard_{$user->id}_" . now()->format('Y-m-d-H-i');
        
        $data = \Illuminate\Support\Facades\Cache::remember($cacheKey, 300, function () use ($user) {
            return $this->getDashboardData($user);
        });

        return view('dashboard', $data);
    }

    /**
     * Get optimized dashboard data
     */
    private function getDashboardData($user): array
    {
        // Today's stats
        // Payments: Count payments created today
        // SMS Sent: Count SMS sent today (based on sms_sent_at date, not created_at)
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        
        $todayPaymentsQuery = Payment::where('user_id', $user->id)
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->selectRaw('
                COUNT(*) as payments,
                COALESCE(SUM(amount), 0) as amount
            ')
            ->first();

        // Count SMS sent today (by sms_sent_at date)
        $todaySmsQuery = Payment::where('user_id', $user->id)
            ->where('sms_sent', true)
            ->whereNotNull('sms_sent_at')
            ->whereBetween('sms_sent_at', [$todayStart, $todayEnd])
            ->selectRaw('COUNT(*) as sms_sent')
            ->first();

        // Count failed SMS for payments created today
        $todayFailedQuery = Payment::where('user_id', $user->id)
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->where('sms_sent', false)
            ->where('sms_retry_count', '>', 0)
            ->selectRaw('COUNT(*) as sms_failed')
            ->first();

        // Count pending SMS for payments created today
        $todayPendingQuery = Payment::where('user_id', $user->id)
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->where('sms_sent', false)
            ->where('sms_retry_count', 0)
            ->selectRaw('COUNT(*) as sms_pending')
            ->first();

        $todayStats = (object) [
            'payments' => (int) ($todayPaymentsQuery->payments ?? 0),
            'amount' => (float) ($todayPaymentsQuery->amount ?? 0),
            'sms_sent' => (int) ($todaySmsQuery->sms_sent ?? 0),
            'sms_failed' => (int) ($todayFailedQuery->sms_failed ?? 0),
            'sms_pending' => (int) ($todayPendingQuery->sms_pending ?? 0),
        ];

        // This month's stats (single query)
        $monthStats = Payment::where('user_id', $user->id)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->selectRaw('COUNT(*) as payments, COALESCE(SUM(amount), 0) as amount')
            ->first();

        // Payment trends (last 7 days, optimized - only daily aggregates)
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

        // Payment status distribution (optimized)
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
        // Failed = SMS was attempted but failed (has retry_count > 0 and not sent)
        // Pending = SMS not sent yet and no retries (might be queued or skipped)
        $smsStats = Payment::where('user_id', $user->id)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN sms_sent = 1 THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN sms_sent = 0 AND sms_retry_count > 0 THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN sms_sent = 0 AND sms_retry_count = 0 THEN 1 ELSE 0 END) as pending
            ')
            ->first();

        // Top merchants (optimized - select only needed columns)
        $topMerchants = Merchant::where('user_id', $user->id)
            ->select('id', 'account_type', 'account_number')
            ->withCount(['payments' => function ($query) {
                $query->where('created_at', '>=', now()->subDays(30));
            }])
            ->orderBy('payments_count', 'desc')
            ->limit(5)
            ->get();

        // Recent payments (optimized - select only needed columns)
        $recentPayments = Payment::where('user_id', $user->id)
            ->select('id', 'merchant_id', 'transaction_id', 'amount', 'status', 'sms_sent', 'sms_retry_count', 'sms_error', 'created_at')
            ->with('merchant:id,account_number')
            ->latest()
            ->limit(10)
            ->get();

        return [
            'todayPayments' => (int) ($todayStats->payments ?? 0),
            'todayAmount' => (float) ($todayStats->amount ?? 0),
            'todaySmsSent' => (int) ($todayStats->sms_sent ?? 0),
            'todaySmsFailed' => (int) ($todayStats->sms_failed ?? 0),
            'todaySmsPending' => (int) ($todayStats->sms_pending ?? 0),
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
