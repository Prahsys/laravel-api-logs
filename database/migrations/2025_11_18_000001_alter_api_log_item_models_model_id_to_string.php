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
        Schema::table('api_log_item_models', function (Blueprint $table) {
            // Change model_id from UUID to string to support both UUID and non-UUID primary keys
            // The original migration used uuidMorphs() but not all models use UUID PKs
            $table->string('model_id')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('api_log_item_models', function (Blueprint $table) {
            // Revert back to UUID type
            $table->uuid('model_id')->change();
        });
    }
};
