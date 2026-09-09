<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates SMS notifications table (one SMS per payment)
     */
    public function up(): void
    {
        Schema::create('sms_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->unique()->constrained('payments')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('merchant_id')->constrained('merchants')->onDelete('cascade');
            
            // SMS Details
            $table->string('phone_number', 20);
            $table->text('message');
            $table->foreignId('template_id')->nullable()->constrained('sms_templates')->onDelete('set null');
            
            // Delivery Status
            $table->enum('status', ['pending', 'sent', 'delivered', 'failed', 'cancelled'])->default('pending');
            $table->string('provider_message_id')->nullable()->comment('Talksasa SMS API message ID');
            $table->json('provider_response')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            
            // Retry Information
            $table->unsignedInteger('attempt_count')->default(0);
            $table->unsignedInteger('max_attempts')->default(3);
            
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index(['status', 'attempt_count', 'max_attempts']);
            $table->index(['provider_message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_notifications');
    }
};
