<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add foreign key constraints that reference tables created later.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Add foreign key for recurring_invoices (created after invoices)
            $table->foreign('recurring_id')
                ->references('id')
                ->on('recurring_invoices')
                ->onDelete('set null');
            
            // Add self-referencing foreign key for parent invoice
            $table->foreign('parent_invoice_id')
                ->references('id')
                ->on('invoices')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['recurring_id']);
            $table->dropForeign(['parent_invoice_id']);
        });
    }
};
