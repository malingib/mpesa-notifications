<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds correlation_id to payments table for end-to-end tracing.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('correlation_id', 100)->nullable()->after('id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['correlation_id']);
            $table->dropColumn('correlation_id');
        });
    }
};
