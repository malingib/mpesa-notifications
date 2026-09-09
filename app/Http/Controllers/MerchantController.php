<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class MerchantController extends Controller
{
    /**
     * Display user's merchants (optimized)
     */
    public function index()
    {
        $user = Auth::user();
        
        // Cache for 5 minutes
        $cacheKey = "user_merchants_{$user->id}_" . now()->format('Y-m-d-H-i');
        
        $data = Cache::remember($cacheKey, 300, function () use ($user) {
            return $this->getMerchantsData($user);
        });

        return view('merchants.index', $data);
    }

    /**
     * Get optimized merchants data
     */
    private function getMerchantsData($user): array
    {
        // Stats (single queries)
        $totalMerchants = Merchant::where('user_id', $user->id)->count();
        $activeMerchants = Merchant::where('user_id', $user->id)->where('is_active', true)->count();

        // Merchants list (optimized - select only needed columns, eager load counts)
        $merchants = Merchant::where('user_id', $user->id)
            ->select('id', 'account_type', 'account_number', 'is_active', 'created_at')
            ->withCount(['payments' => function ($query) {
                $query->where('created_at', '>=', now()->subDays(30));
            }])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return [
            'totalMerchants' => $totalMerchants,
            'activeMerchants' => $activeMerchants,
            'merchants' => $merchants,
        ];
    }
}
