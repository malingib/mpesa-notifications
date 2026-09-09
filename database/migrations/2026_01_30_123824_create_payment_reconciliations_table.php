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
        Schema::create('payment_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('payment_id')->constrained('payments')->onDelete('cascade');
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->onDelete('set null')->comment('NULL if unmatched');
            $table->enum('match_type', ['auto', 'manual'])->default('auto');
            $table->enum('match_confidence', ['high', 'medium', 'low'])->nullable()->comment('For auto-matches');
            $table->foreignId('matched_by')->nullable()->constrained('users')->onDelete('set null')->comment('User ID who matched (NULL for auto)');
            $table->timestamp('matched_at')->nullable()->comment('When match occurred');
            $table->decimal('amount_matched', 15, 2)->comment('Amount matched');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index('payment_id');
            $table->index('invoice_id');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_reconciliations');
    }
};
