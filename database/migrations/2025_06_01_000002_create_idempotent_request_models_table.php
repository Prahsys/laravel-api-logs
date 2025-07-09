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
        Schema::create('idempotent_request_models', function (Blueprint $table) {
            $table->id();
            $table->uuid('idempotent_request_id');
            $table->uuidMorphs('model');
            $table->timestamps();

            $table->foreign('idempotent_request_id')
                ->references('id')
                ->on('idempotent_requests')
                ->cascadeOnDelete();

            // Unique constraint to prevent duplicate associations
            $table->unique(['idempotent_request_id', 'model_type', 'model_id'], 'unique_idempotent_request_model');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('idempotent_request_models');
    }
};
