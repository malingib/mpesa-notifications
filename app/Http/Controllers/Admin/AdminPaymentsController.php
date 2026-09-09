<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Support\Facades\Cache;

class AdminPaymentsController extends Controller
{
    /**
     * Display all payments (optimized)
     */
    public function index()
    {
        // Cache for 5 minutes
        $cacheKey = 'admin_payments_' . now()->format('Y-m-d-H-i');
        
        $data = Cache::remember($cacheKey, 300, function () {
            return $this->getPaymentsData();
        });

        return view('admin.payments', $data);
    }

    /**
     * Get optimized payments data
     */
    private function getPaymentsData(): array
    {
        // Stats (single queries)
        $totalPayments = Payment::withoutGlobalScopes()->count();
        $totalAmount = Payment::withoutGlobalScopes()->sum('amount');
        $smsSent = Payment::withoutGlobalScopes()->where('sms_sent', true)->count();

        // Payments list (optimized - select only needed columns, eager load relationships)
        $payments = Payment::withoutGlobalScopes()
            ->select('id', 'user_id', 'merchant_id', 'transaction_id', 'amount', 'status', 'sms_sent', 'created_at')
            ->with(['user:id,name', 'merchant:id,account_number'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return [
            'totalPayments' => $totalPayments,
            'totalAmount' => $totalAmount,
            'smsSent' => $smsSent,
            'payments' => $payments,
        ];
    }
}
