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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade')->comment('Tenant ID');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null')->comment('Customer ID (nullable for one-off invoices)');
            $table->string('invoice_number', 50)->comment('Unique invoice number (e.g., INV-2026-001)');
            $table->enum('status', ['draft', 'sent', 'viewed', 'paid', 'partial', 'overdue', 'cancelled'])->default('draft');
            $table->date('issue_date')->comment('Invoice issue date');
            $table->date('due_date')->comment('Payment due date');
            $table->date('paid_date')->nullable()->comment('Date fully paid');
            $table->decimal('subtotal', 15, 2)->default(0.00)->comment('Subtotal before tax');
            $table->decimal('tax_rate', 5, 2)->default(0.00)->comment('Tax rate percentage (e.g., 16 for VAT)');
            $table->decimal('tax_amount', 15, 2)->default(0.00)->comment('Tax amount');
            $table->decimal('discount_amount', 15, 2)->default(0.00)->comment('Discount amount');
            $table->decimal('total_amount', 15, 2)->default(0.00)->comment('Total amount due');
            $table->decimal('paid_amount', 15, 2)->default(0.00)->comment('Amount paid so far');
            $table->decimal('balance', 15, 2)->default(0.00)->comment('Remaining balance');
            $table->string('currency', 3)->default('KES');
            $table->text('notes')->nullable()->comment('Invoice notes visible to customer');
            $table->text('terms')->nullable()->comment('Payment terms');
            $table->string('reference', 255)->nullable()->comment('Reference number for payment matching');
            $table->unsignedBigInteger('recurring_id')->nullable()->comment('If part of recurring invoice series');
            $table->unsignedBigInteger('parent_invoice_id')->nullable()->comment('If credit note or amendment');
            $table->string('pdf_path', 500)->nullable()->comment('Generated PDF path');
            $table->timestamp('sent_at')->nullable()->comment('When invoice was sent');
            $table->timestamp('viewed_at')->nullable()->comment('When customer viewed invoice');
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['user_id', 'invoice_number'], 'unique_invoice_number');
            $table->index(['user_id', 'status']);
            $table->index('customer_id');
            $table->index('due_date');
            $table->index('reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
