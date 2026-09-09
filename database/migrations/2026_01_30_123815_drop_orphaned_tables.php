<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Drop tables that were partially created during failed migration.
     */
    public function up(): void
    {
        // Drop tables if they exist (from failed migration)
        DB::statement('DROP TABLE IF EXISTS invoice_items');
        DB::statement('DROP TABLE IF EXISTS invoice_payments');
        DB::statement('DROP TABLE IF EXISTS payment_reconciliations');
        DB::statement('DROP TABLE IF EXISTS recurring_invoices');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Nothing to do - these tables will be recreated by their respective migrations
    }
};
