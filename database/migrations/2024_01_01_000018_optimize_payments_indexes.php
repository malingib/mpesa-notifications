<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Optimizes indexes for high-volume payment processing.
     * Creates composite indexes for idempotency and common queries.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Drop individual indexes if they exist (will be replaced by composite)
            // Note: transaction_id and receipt_number are UNIQUE, so we keep those
            
            // Add composite index for idempotency checks (single query optimization)
            // This allows: WHERE transaction_id = ? OR receipt_number = ? OR request_id = ?
            if (!$this->indexExists('payments', 'idx_payments_idempotency')) {
                $table->index(['transaction_id', 'receipt_number', 'request_id'], 'idx_payments_idempotency');
            }

            // Composite index for tenant-scoped queries (covers most common queries)
            if (!$this->indexExists('payments', 'idx_payments_tenant_status_time')) {
                $table->index(['user_id', 'status', 'created_at'], 'idx_payments_tenant_status_time');
            }

            // Index for SMS retry queries
            if (!$this->indexExists('payments', 'idx_payments_sms_retry')) {
                $table->index(['user_id', 'sms_sent', 'status', 'sms_retry_count'], 'idx_payments_sms_retry');
            }

            // Index for merchant queries
            if (!$this->indexExists('payments', 'idx_payments_merchant_time')) {
                $table->index(['merchant_id', 'created_at'], 'idx_payments_merchant_time');
            }

            // Index for correlation ID (audit trail queries)
            if (!$this->indexExists('payments', 'idx_payments_correlation')) {
                $table->index(['correlation_id'], 'idx_payments_correlation');
            }
        });

        // Optimize merchants table for fast lookups
        Schema::table('merchants', function (Blueprint $table) {
            // Composite unique index for account lookup (cached queries)
            if (!$this->indexExists('merchants', 'idx_merchants_account_lookup')) {
                $table->index(['account_type', 'account_number', 'is_active'], 'idx_merchants_account_lookup');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('idx_payments_idempotency');
            $table->dropIndex('idx_payments_tenant_status_time');
            $table->dropIndex('idx_payments_sms_retry');
            $table->dropIndex('idx_payments_merchant_time');
            $table->dropIndex('idx_payments_correlation');
        });

        Schema::table('merchants', function (Blueprint $table) {
            $table->dropIndex('idx_merchants_account_lookup');
        });
    }

    /**
     * Check if index exists
     */
    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();
        
        try {
            $result = $connection->select(
                "SELECT COUNT(*) as count FROM information_schema.statistics 
                 WHERE table_schema = ? AND table_name = ? AND index_name = ?",
                [$database, $table, $index]
            );
            
            return $result[0]->count > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
};
