<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds SMS enable/disable settings to users and merchants
     */
    public function up(): void
    {
        // Add SMS settings to users table
        if (!Schema::hasColumn('users', 'sms_enabled')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('sms_enabled')->default(true)->after('rate_limit_per_hour');
            });
        }

        // Add SMS settings to merchants table
        if (!Schema::hasColumn('merchants', 'sms_enabled')) {
            Schema::table('merchants', function (Blueprint $table) {
                $table->boolean('sms_enabled')->default(true)->after('is_active');
            });
        }

        // Ensure sms_template_id exists (should already exist from previous migration)
        if (!Schema::hasColumn('merchants', 'sms_template_id')) {
            Schema::table('merchants', function (Blueprint $table) {
                $table->foreignId('sms_template_id')
                    ->nullable()
                    ->after('sms_enabled')
                    ->constrained('sms_templates')
                    ->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'sms_enabled')) {
                $table->dropColumn('sms_enabled');
            }
        });

        Schema::table('merchants', function (Blueprint $table) {
            if (Schema::hasColumn('merchants', 'sms_enabled')) {
                $table->dropColumn('sms_enabled');
            }
        });
    }
};
