<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates SMS attempts table to track every SMS send attempt.
     * Provides complete history of SMS delivery attempts.
     */
    public function up(): void
    {
        // Drop table if it exists (from failed migration)
        Schema::dropIfExists('sms_attempts');
        
        Schema::create('sms_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            
            // Correlation and job tracking
            $table->string('correlation_id', 100)->index()->comment('End-to-end trace ID');
            $table->string('job_id', 100)->nullable()->index()->comment('Queue job UUID');
            $table->integer('attempt_number')->default(1)->comment('Retry attempt number');
            
            // SMS details
            $table->string('phone_number', 20)->comment('Recipient phone (may be masked)');
            $table->text('message')->nullable()->comment('SMS message content (optional for privacy)');
            $table->integer('message_length')->nullable()->comment('Character count');
            
            // Status and result
            $table->enum('status', ['pending', 'sent', 'failed', 'skipped'])->default('pending');
            $table->string('error_code', 50)->nullable();
            $table->text('error_message')->nullable();
            $table->json('provider_response')->nullable()->comment('Raw API response');
            
            // Timestamps
            $table->timestamp('sent_at')->nullable()->comment('When SMS was actually sent');
            $table->timestamp('created_at')->index();
            $table->timestamp('updated_at')->nullable();
            
            // Indexes for common queries
            $table->index(['payment_id', 'created_at']);
            $table->index(['correlation_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_attempts');
    }
};
