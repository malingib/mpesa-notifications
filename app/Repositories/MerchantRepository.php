<?php

namespace App\Repositories;

use App\Models\Merchant;
use Illuminate\Database\Eloquent\Collection;

/**
 * Merchant Repository
 * 
 * Handles data access for merchants (payment accounts) with tenant isolation.
 */
class MerchantRepository
{
    /**
     * Find merchant by account type and number
     * 
     * @param string $accountType 'paybill' or 'till'
     * @param string $accountNumber The Paybill/Till number
     * @return Merchant|null
     */
    public function findByAccount(string $accountType, string $accountNumber): ?Merchant
    {
        // Disable global scopes to find merchant across all tenants
        // Webhooks need to find merchants regardless of tenant context
        return Merchant::withoutGlobalScopes()
            ->where('account_type', $accountType)
            ->where('account_number', $accountNumber)
            ->where('is_active', true)
            ->with('user')
            ->first();
    }

    /**
     * Get all active merchants for a user
     * 
     * @param int $userId
     * @return Collection
     */
    public function getByUser(int $userId): Collection
    {
        return Merchant::where('user_id', $userId)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Check if merchant account exists and is active
     * 
     * @param string $accountType
     * @param string $accountNumber
     * @return bool
     */
    public function accountExists(string $accountType, string $accountNumber): bool
    {
        return Merchant::where('account_type', $accountType)
            ->where('account_number', $accountNumber)
            ->where('is_active', true)
            ->exists();
    }
}
