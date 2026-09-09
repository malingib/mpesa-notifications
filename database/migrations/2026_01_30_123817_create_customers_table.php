<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade')->comment('Tenant ID');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable()->comment('Phone number (254XXXXXXXXX)');
            $table->string('company')->nullable();
            $table->string('tax_id', 50)->nullable()->comment('Tax ID/KRA PIN');
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('country', 100)->default('Kenya');
            $table->text('notes')->nullable()->comment('Internal notes');
            $table->json('tags')->nullable()->comment('Customer tags for segmentation');
            $table->enum('status', ['active', 'inactive', 'archived'])->default('active');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['user_id', 'status']);
            $table->index('email');
            $table->index('phone');
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
