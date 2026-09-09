<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds foreign key constraint from payments to sms_notifications
     * Created separately to avoid circular dependency
     */
    public function up(): void
    {
        // Only add foreign key if column exists
        if (Schema::hasColumn('payments', 'sms_notification_id')) {
            Schema::table('payments', function (Blueprint $table) {
                // Check if foreign key already exists
                $foreignKeys = Schema::getConnection()
                    ->getDoctrineSchemaManager()
                    ->listTableForeignKeys('payments');
                
                $hasForeignKey = false;
                foreach ($foreignKeys as $foreignKey) {
                    if (in_array('sms_notification_id', $foreignKey->getLocalColumns())) {
                        $hasForeignKey = true;
                        break;
                    }
                }
                
                if (!$hasForeignKey) {
                    $table->foreign('sms_notification_id')
                        ->references('id')
                        ->on('sms_notifications')
                        ->onDelete('set null');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payments', 'sms_notification_id')) {
            Schema::table('payments', function (Blueprint $table) {
                try {
                    $table->dropForeign(['sms_notification_id']);
                } catch (\Exception $e) {
                    // Foreign key might not exist
                }
            });
        }
    }
};
