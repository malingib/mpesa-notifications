<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Tenant Scope
 * 
 * Global scope that automatically filters queries by tenant (user_id).
 * Applied to all tenant-scoped models.
 */
class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $query, Model $model): void
    {
        $tenantId = $this->getTenantId();

        if ($tenantId) {
            $column = $model->getTable() . '.user_id';
            $query->where($column, $tenantId);
        }
    }

    /**
     * Get current tenant ID
     */
    protected function getTenantId(): ?int
    {
        // Get from config (set by TenantIsolationMiddleware)
        return config('app.current_tenant_id') 
            ?? request()->get('user_id')
            ?? request()->get('tenant_id');
    }
}
