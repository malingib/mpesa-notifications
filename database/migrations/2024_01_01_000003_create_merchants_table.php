<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates merchants table (Paybill & Till numbers)
     */
    public function up(): void
    {
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('account_type', ['paybill', 'till']);
            $table->string('account_number', 20);
            $table->string('account_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('sms_template_id')->nullable()->constrained('sms_templates')->onDelete('set null');
            $table->string('webhook_url', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['account_type', 'account_number']);
            $table->index(['user_id', 'is_active']);
            $table->index(['account_type', 'account_number', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchants');
    }
};
