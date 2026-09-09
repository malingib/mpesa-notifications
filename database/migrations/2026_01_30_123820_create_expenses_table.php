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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade')->comment('Tenant ID');
            $table->foreignId('category_id')->nullable()->constrained('expense_categories')->onDelete('set null')->comment('Expense category');
            $table->string('vendor')->nullable()->comment('Vendor/supplier name');
            $table->string('description', 500)->comment('Expense description');
            $table->decimal('amount', 15, 2)->comment('Expense amount');
            $table->decimal('tax_amount', 15, 2)->default(0.00)->comment('Tax/VAT amount');
            $table->decimal('total_amount', 15, 2)->comment('Total including tax');
            $table->enum('payment_method', ['mpesa', 'bank', 'cash', 'card', 'other'])->default('mpesa');
            $table->string('payment_reference', 255)->nullable()->comment('Payment reference/receipt');
            $table->date('expense_date')->comment('Date expense occurred');
            $table->string('receipt_path', 500)->nullable()->comment('Receipt image/document path');
            $table->enum('status', ['pending', 'paid', 'reimbursed'])->default('paid');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['user_id', 'expense_date']);
            $table->index('category_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
