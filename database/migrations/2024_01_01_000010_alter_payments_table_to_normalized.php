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
     * Alters existing payments table to match normalized schema.
     * Since this is a development system, we'll drop and recreate if needed.
     */
    public function up(): void
    {
        // Check if old columns exist
        $hasTenantId = Schema::hasColumn('payments', 'tenant_id');
        $hasUserId = Schema::hasColumn('payments', 'user_id');

        if ($hasTenantId && !$hasUserId) {
            // Check if there's any data
            $paymentCount = DB::table('payments')->count();

            if ($paymentCount > 0) {
                // If there's data, we need to migrate it
                // Add new columns first
                Schema::table('payments', function (Blueprint $table) {
                    $table->unsignedBigInteger('user_id')->nullable()->after('id');
                    $table->unsignedBigInteger('merchant_id')->nullable()->after('user_id');
                });

                // Migrate data: tenant_id -> user_id (assuming 1:1 mapping)
                DB::statement('UPDATE payments SET user_id = tenant_id WHERE tenant_id IS NOT NULL');
                
                // Migrate data: payment_account_id -> merchant_id (assuming 1:1 mapping)
                DB::statement('UPDATE payments SET merchant_id = payment_account_id WHERE payment_account_id IS NOT NULL');

                // Now alter the table structure
                Schema::table('payments', function (Blueprint $table) {
                    // Make columns NOT NULL
                    $table->unsignedBigInteger('user_id')->nullable(false)->change();
                    $table->unsignedBigInteger('merchant_id')->nullable(false)->change();
                    
                    // Add foreign keys
                    $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                    $table->foreign('merchant_id')->references('id')->on('merchants')->onDelete('cascade');
                    
                    // Drop old foreign keys
                    try {
                        $table->dropForeign(['payments_tenant_id_foreign']);
                    } catch (\Exception $e) {
                        // Foreign key might not exist or have different name
                    }
                    try {
                        $table->dropForeign(['payments_payment_account_id_foreign']);
                    } catch (\Exception $e) {
                        // Foreign key might not exist or have different name
                    }
                    
                    // Drop old columns
                    $table->dropColumn(['tenant_id', 'payment_account_id']);
                });
            } else {
                // No data, drop foreign keys first, then drop and recreate
                // Get actual foreign key names from database
                $foreignKeys = DB::select("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'sms_notifications' 
                    AND REFERENCED_TABLE_NAME = 'payments'
                ");
                
                foreach ($foreignKeys as $fk) {
                    try {
                        DB::statement("ALTER TABLE sms_notifications DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
                    } catch (\Exception $e) {
                        // Ignore if already dropped or doesn't exist
                    }
                }
                
                Schema::dropIfExists('payments');
                
                Schema::create('payments', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                    $table->foreignId('merchant_id')->constrained('merchants')->onDelete('cascade');
                    
                    // Idempotency Fields
                    $table->string('transaction_id', 100)->unique()->comment('M-Pesa TransactionID');
                    $table->string('receipt_number', 100)->nullable()->unique()->comment('M-Pesa ReceiptNumber');
                    $table->string('request_id', 100)->nullable();
                    $table->string('conversation_id', 100)->nullable();
                    
                    // Payment Details
                    $table->enum('account_type', ['paybill', 'till']);
                    $table->string('account_number', 20);
                    $table->decimal('amount', 15, 2);
                    $table->string('currency', 3)->default('KES');
                    $table->string('phone_number', 20);
                    $table->string('payer_name')->nullable();
                    
                    // Transaction Metadata
                    $table->timestamp('transaction_time');
                    $table->enum('status', ['pending', 'completed', 'failed', 'cancelled', 'reversed'])->default('pending');
                    $table->text('description')->nullable();
                    $table->string('reference')->nullable();
                    $table->json('metadata')->comment('Full M-Pesa payload');
                    
                    // SMS Tracking
                    $table->boolean('sms_sent')->default(false);
                    $table->timestamp('sms_sent_at')->nullable();
                    $table->unsignedBigInteger('sms_notification_id')->nullable();
                    $table->text('sms_error')->nullable();
                    $table->unsignedInteger('sms_retry_count')->default(0);
                    
                    $table->timestamps();
                    $table->softDeletes();
                    
                    // Indexes
                    $table->index(['user_id', 'status']);
                    $table->index(['merchant_id', 'transaction_time']);
                    $table->index(['transaction_time']);
                    $table->index(['sms_sent', 'status', 'sms_retry_count']);
                    $table->index(['request_id']);
                    $table->index(['account_type', 'account_number', 'transaction_time']);
                });
            }
        }

        // Add new fields if they don't exist
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'conversation_id')) {
                $table->string('conversation_id', 100)->nullable()->after('request_id');
            }
            if (!Schema::hasColumn('payments', 'reference')) {
                $table->string('reference')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        // Reverse migration not implemented
        // Would need to restore tenant_id and payment_account_id
    }
};
