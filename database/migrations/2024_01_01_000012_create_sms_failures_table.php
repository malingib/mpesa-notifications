<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates table for tracking SMS failures (optional, for analytics)
     */
    public function up(): void
    {
        Schema::create('sms_failures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            $table->string('phone_number', 20);
            $table->text('message')->nullable();
            $table->text('error_message');
            $table->string('error_code')->nullable();
            $table->boolean('is_retryable')->default(true);
            $table->integer('attempt_count')->default(0);
            $table->timestamp('first_failed_at');
            $table->timestamp('last_failed_at');
            $table->timestamp('resolved_at')->nullable();
            
            $table->timestamps();
            
            $table->index(['user_id', 'resolved_at']);
            $table->index(['payment_id']);
            $table->index(['is_retryable', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_failures');
    }
};
