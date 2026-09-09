<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates immutable payment history table for audit trail.
     * Tracks all changes to payment records.
     */
    public function up(): void
    {
        Schema::create('payment_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->onDelete('cascade');
            $table->string('correlation_id', 100)->index()->comment('End-to-end trace ID');
            
            // Change tracking
            $table->string('action', 50)->comment('created, updated, status_changed, sms_sent, etc.');
            $table->string('changed_by_type', 20)->default('system')->comment('system, user, api');
            $table->unsignedBigInteger('changed_by_id')->nullable()->comment('User ID or system identifier');
            
            // Field-level change tracking
            $table->string('field_name', 100)->nullable()->comment('Field that changed');
            $table->json('old_value')->nullable()->comment('Previous value');
            $table->json('new_value')->nullable()->comment('New value');
            
            // Context
            $table->text('reason')->nullable()->comment('Why the change was made');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('session_id', 100)->nullable();
            $table->string('request_id', 100)->nullable()->comment('HTTP request ID');
            
            // Metadata
            $table->json('metadata')->nullable()->comment('Additional context');
            
            $table->timestamp('created_at')->index();
            
            // Indexes for common queries
            $table->index(['payment_id', 'created_at']);
            $table->index(['correlation_id', 'created_at']);
            $table->index(['action', 'created_at']);
            $table->index(['changed_by_type', 'changed_by_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_history');
    }
};
