<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates table to track GDPR anonymization operations.
     */
    public function up(): void
    {
        Schema::create('data_anonymizations', function (Blueprint $table) {
            $table->id();
            
            // Entity reference
            $table->string('entity_type', 100)->comment('Payment, User, etc.');
            $table->unsignedBigInteger('entity_id')->comment('Entity ID');
            
            // Anonymization details
            $table->timestamp('anonymized_at');
            $table->string('anonymized_by_type', 20)->default('system')->comment('system, user');
            $table->unsignedBigInteger('anonymized_by_id')->nullable()->comment('User ID if manual');
            
            // Reason and scope
            $table->enum('reason', ['gdpr_request', 'retention_policy', 'manual', 'other'])->default('retention_policy');
            $table->text('reason_details')->nullable();
            $table->json('fields_anonymized')->comment('List of anonymized fields');
            
            // Metadata
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['entity_type', 'entity_id']);
            $table->index(['anonymized_at']);
            $table->index(['reason', 'anonymized_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_anonymizations');
    }
};
