<?php

namespace App\Repositories;

use App\Models\PaymentAccount;
use Illuminate\Database\Eloquent\Collection;

/**
 * Payment Account Repository
 * 
 * Handles data access for payment accounts with tenant isolation.
 */
class PaymentAccountRepository
{
    /**
     * Find payment account by account type and number
     * 
     * @param string $accountType 'paybill' or 'till'
     * @param string $accountNumber The Paybill/Till number
     * @return PaymentAccount|null
     */
    public function findByAccount(string $accountType, string $accountNumber): ?PaymentAccount
    {
        return PaymentAccount::where('account_type', $accountType)
            ->where('account_number', $accountNumber)
            ->where('is_active', true)
            ->with('tenant')
            ->first();
    }

    /**
     * Get all active payment accounts for a tenant
     * 
     * @param int $tenantId
     * @return Collection
     */
    public function getByTenant(int $tenantId): Collection
    {
        return PaymentAccount::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Check if account exists and is active
     * 
     * @param string $accountType
     * @param string $accountNumber
     * @return bool
     */
    public function accountExists(string $accountType, string $accountNumber): bool
    {
        return PaymentAccount::where('account_type', $accountType)
            ->where('account_number', $accountNumber)
            ->where('is_active', true)
            ->exists();
    }
}
