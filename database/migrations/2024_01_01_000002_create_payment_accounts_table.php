<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Maps Paybill/Till numbers to tenants
     */
    public function up(): void
    {
        Schema::create('payment_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->enum('account_type', ['paybill', 'till']); // Paybill or Till number
            $table->string('account_number', 20); // The actual Paybill/Till number
            $table->string('account_name')->nullable(); // Display name
            $table->boolean('is_active')->default(true);
            $table->json('sms_template')->nullable(); // Custom SMS template per account
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['account_type', 'account_number']);
            $table->index('tenant_id');
            $table->index(['account_type', 'account_number', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_accounts');
    }
};
