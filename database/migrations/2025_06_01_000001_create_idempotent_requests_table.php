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
        Schema::create('idempotent_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('request_id')->unique()->index();
            $table->string('path');
            $table->string('method');
            $table->string('api_version')->nullable();
            $table->timestamp('request_at');
            $table->timestamp('response_at')->nullable();
            $table->integer('response_status')->nullable();
            $table->boolean('is_error')->default(false);
            $table->timestamps();

            // Indexes for common queries
            $table->index(['path', 'method']);
            $table->index('request_at');
            $table->index('is_error');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('idempotent_requests');
    }
};
