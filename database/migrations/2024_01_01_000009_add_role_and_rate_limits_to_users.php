<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds role and rate limiting fields to users table
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'client'])->default('client')->after('is_active');
            $table->unsignedInteger('rate_limit_per_minute')->default(60)->after('role');
            $table->unsignedInteger('rate_limit_per_hour')->default(1000)->after('rate_limit_per_minute');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'rate_limit_per_minute', 'rate_limit_per_hour']);
        });
    }
};
