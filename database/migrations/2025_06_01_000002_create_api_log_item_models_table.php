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
        Schema::create('api_log_item_models', function (Blueprint $table) {
            $table->id();
            $table->uuid('api_log_item_id');
            $table->uuidMorphs('model');
            $table->timestamps();

            $table->foreign('api_log_item_id')
                ->references('id')
                ->on('api_log_items')
                ->cascadeOnDelete();

            // Unique constraint to prevent duplicate associations
            $table->unique(['api_log_item_id', 'model_type', 'model_id'], 'unique_api_log_item_model');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_log_item_models');
    }
};
