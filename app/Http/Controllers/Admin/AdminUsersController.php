<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class AdminUsersController extends Controller
{
    /**
     * Display all users (optimized)
     */
    public function index()
    {
        // Cache for 5 minutes
        $cacheKey = 'admin_users_' . now()->format('Y-m-d-H-i');
        
        $data = Cache::remember($cacheKey, 300, function () {
            return $this->getUsersData();
        });

        return view('admin.users', $data);
    }

    /**
     * Get optimized users data
     */
    private function getUsersData(): array
    {
        // Stats (single queries)
        $totalUsers = User::withoutGlobalScopes()->count();
        $activeUsers = User::withoutGlobalScopes()->where('is_active', true)->count();
        $totalClients = User::withoutGlobalScopes()->where('role', 'client')->count();
        $totalAdmins = User::withoutGlobalScopes()->where('role', 'admin')->count();

        // Users list (optimized - select only needed columns, eager load counts)
        $users = User::withoutGlobalScopes()
            ->select('id', 'name', 'email', 'role', 'is_active', 'created_at')
            ->withCount(['payments' => function ($query) {
                $query->withoutGlobalScopes();
            }])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return [
            'totalUsers' => $totalUsers,
            'activeUsers' => $activeUsers,
            'totalClients' => $totalClients,
            'totalAdmins' => $totalAdmins,
            'users' => $users,
        ];
    }
}
