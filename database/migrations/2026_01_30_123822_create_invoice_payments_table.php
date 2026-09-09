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
        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('cascade');
            $table->foreignId('payment_id')->constrained('payments')->onDelete('cascade')->comment('Link to payments table');
            $table->decimal('amount', 15, 2)->comment('Amount applied to invoice');
            $table->date('payment_date')->comment('Date payment was applied');
            $table->text('notes')->nullable()->comment('Payment notes');
            $table->timestamps();
            
            $table->index('invoice_id');
            $table->index('payment_id');
            $table->unique(['invoice_id', 'payment_id'], 'unique_invoice_payment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
    }
};
