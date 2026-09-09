<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Stores all payment transactions with idempotency support
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('payment_account_id')->constrained('payment_accounts')->onDelete('cascade');
            
            // M-Pesa transaction identifiers (for idempotency)
            $table->string('transaction_id')->unique(); // M-Pesa TransactionID
            $table->string('receipt_number')->nullable()->unique(); // M-Pesa ReceiptNumber
            $table->string('request_id')->nullable()->index(); // M-Pesa RequestID (for duplicate detection)
            
            // Payment details
            $table->enum('account_type', ['paybill', 'till']);
            $table->string('account_number', 20);
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('KES');
            $table->string('phone_number', 20); // Payer phone number
            $table->string('payer_name')->nullable();
            
            // Transaction metadata
            $table->timestamp('transaction_time'); // When payment occurred
            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // Store full M-Pesa payload for audit
            
            // SMS notification tracking
            $table->boolean('sms_sent')->default(false);
            $table->timestamp('sms_sent_at')->nullable();
            $table->text('sms_error')->nullable();
            $table->integer('sms_retry_count')->default(0);
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['tenant_id', 'status']);
            $table->index(['payment_account_id', 'transaction_time']);
            $table->index(['transaction_time']);
            $table->index(['sms_sent', 'status']);
            // Note: request_id index already created above on line 24
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
