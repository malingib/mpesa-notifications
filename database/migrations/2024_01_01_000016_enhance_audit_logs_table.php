<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Enhances audit_logs table with correlation IDs and additional fields.
     */
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            // Add correlation ID for end-to-end tracing
            if (!Schema::hasColumn('audit_logs', 'correlation_id')) {
                $table->string('correlation_id', 100)->nullable()->after('id')->index();
            }
            
            // Add severity and category
            if (!Schema::hasColumn('audit_logs', 'severity')) {
                $table->enum('severity', ['info', 'warning', 'error', 'critical'])->default('info')->after('description');
            }
            
            if (!Schema::hasColumn('audit_logs', 'category')) {
                $table->string('category', 50)->nullable()->after('severity')->index()->comment('payment, sms, auth, system');
            }
            
            // Add session and request tracking
            if (!Schema::hasColumn('audit_logs', 'session_id')) {
                $table->string('session_id', 100)->nullable()->after('user_agent');
            }
            
            if (!Schema::hasColumn('audit_logs', 'request_id')) {
                $table->string('request_id', 100)->nullable()->after('session_id')->index()->comment('HTTP request ID');
            }
        });
        
        // Add additional indexes
        Schema::table('audit_logs', function (Blueprint $table) {
            if (!$this->indexExists('audit_logs', 'audit_logs_correlation_id_created_at_index')) {
                $table->index(['correlation_id', 'created_at']);
            }
            
            if (!$this->indexExists('audit_logs', 'audit_logs_category_created_at_index')) {
                $table->index(['category', 'created_at']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['correlation_id']);
            $table->dropIndex(['correlation_id', 'created_at']);
            $table->dropIndex(['category']);
            $table->dropIndex(['category', 'created_at']);
            $table->dropIndex(['request_id']);
            
            $table->dropColumn(['correlation_id', 'severity', 'category', 'session_id', 'request_id']);
        });
    }
    
    /**
     * Check if index exists
     */
    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();
        
        $result = $connection->select(
            "SELECT COUNT(*) as count FROM information_schema.statistics 
             WHERE table_schema = ? AND table_name = ? AND index_name = ?",
            [$database, $table, $index]
        );
        
        return $result[0]->count > 0;
    }
};
