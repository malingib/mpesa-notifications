<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class PaymentController extends Controller
{
    /**
     * Display user's payments with filters and analytics
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Get filter parameters
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $viewMode = $request->input('view', 'list'); // list, top_days, top_months
        
        $data = $this->getPaymentsData($user, $dateFrom, $dateTo, $viewMode);

        return view('payments.index', $data);
    }

    /**
     * Calculate earnings for different time periods (AJAX endpoint)
     */
    public function calculateEarnings(Request $request)
    {
        $user = Auth::user();
        $period = $request->input('period', 'all'); // daily, monthly, annual, all
        
        $earnings = $this->calculateEarningsByPeriod($user, $period);
        
        return response()->json($earnings);
    }

    /**
     * Get optimized payments data with filters
     */
    private function getPaymentsData($user, $dateFrom = null, $dateTo = null, $viewMode = 'list'): array
    {
        $query = Payment::where('user_id', $user->id);
        
        // Apply date filters
        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        // Get stats for filtered period
        $stats = (clone $query)->selectRaw('
            COUNT(*) as total_payments,
            COALESCE(SUM(amount), 0) as total_amount,
            SUM(CASE WHEN sms_sent = 1 THEN 1 ELSE 0 END) as sms_sent
        ')->first();

        // Get top revenue days
        $topDays = Payment::where('user_id', $user->id)
            ->selectRaw('
                DATE(created_at) as date,
                COUNT(*) as count,
                COALESCE(SUM(amount), 0) as total
            ')
            ->groupBy('date')
            ->orderBy('total', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'date' => \Carbon\Carbon::parse($item->date)->format('M d, Y'),
                    'count' => (int) $item->count,
                    'total' => (float) $item->total,
                ];
            });

        // Get top revenue months
        $topMonths = Payment::where('user_id', $user->id)
            ->selectRaw('
                DATE_FORMAT(created_at, "%Y-%m") as month,
                DATE_FORMAT(created_at, "%M %Y") as month_label,
                COUNT(*) as count,
                COALESCE(SUM(amount), 0) as total
            ')
            ->groupBy('month', 'month_label')
            ->orderBy('total', 'desc')
            ->limit(12)
            ->get()
            ->map(function ($item) {
                return [
                    'month' => $item->month,
                    'label' => $item->month_label,
                    'count' => (int) $item->count,
                    'total' => (float) $item->total,
                ];
            });

        // Get payments list or top revenue data based on view mode
        if ($viewMode === 'top_days') {
            $payments = null;
            $topRevenueData = $topDays;
        } elseif ($viewMode === 'top_months') {
            $payments = null;
            $topRevenueData = $topMonths;
        } else {
            // Default: paginated list
            $payments = (clone $query)
                ->select('id', 'merchant_id', 'transaction_id', 'amount', 'status', 'sms_sent', 'sms_retry_count', 'sms_error', 'payer_name', 'phone_number', 'created_at')
                ->with('merchant:id,account_number,account_type')
                ->orderBy('created_at', 'desc')
                ->paginate(10)
                ->withQueryString(); // Preserve query parameters in pagination
            $topRevenueData = null;
        }

        return [
            'totalPayments' => (int) ($stats->total_payments ?? 0),
            'totalAmount' => (float) ($stats->total_amount ?? 0),
            'smsSent' => (int) ($stats->sms_sent ?? 0),
            'payments' => $payments,
            'topDays' => $topDays,
            'topMonths' => $topMonths,
            'topRevenueData' => $topRevenueData,
            'viewMode' => $viewMode,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ];
    }

    /**
     * Calculate earnings by period
     */
    private function calculateEarningsByPeriod($user, string $period): array
    {
        $query = Payment::where('user_id', $user->id);
        
        switch ($period) {
            case 'daily':
                $startDate = now()->startOfDay();
                $endDate = now()->endOfDay();
                $label = 'Today';
                break;
                
            case 'monthly':
                $startDate = now()->startOfMonth();
                $endDate = now()->endOfMonth();
                $label = 'This Month (' . now()->format('M Y') . ')';
                break;
                
            case 'annual':
                $startDate = now()->startOfYear();
                $endDate = now()->endOfYear();
                $label = 'This Year (' . now()->year . ')';
                break;
                
            case 'all':
            default:
                $startDate = null;
                $endDate = null;
                $label = 'All Time';
                break;
        }
        
        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }
        
        $result = $query->selectRaw('
            COUNT(*) as count,
            COALESCE(SUM(amount), 0) as total,
            COALESCE(AVG(amount), 0) as average,
            COALESCE(MAX(amount), 0) as max_amount,
            COALESCE(MIN(amount), 0) as min_amount
        ')->first();
        
        return [
            'success' => true,
            'period' => $period,
            'label' => $label,
            'count' => (int) ($result->count ?? 0),
            'total' => (float) ($result->total ?? 0),
            'average' => (float) ($result->average ?? 0),
            'max_amount' => (float) ($result->max_amount ?? 0),
            'min_amount' => (float) ($result->min_amount ?? 0),
            'formatted_total' => 'KES ' . number_format($result->total ?? 0, 2),
            'formatted_average' => 'KES ' . number_format($result->average ?? 0, 2),
            'formatted_max' => 'KES ' . number_format($result->max_amount ?? 0, 2),
            'formatted_min' => 'KES ' . number_format($result->min_amount ?? 0, 2),
        ];
    }
}
