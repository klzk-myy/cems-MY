<?php

/**
 * Consolidated Migration: Exchange Rate History Tables
 * Replaces: 2026_04_02_000001_create_exchange_rate_histories_table
 *
 * Creates: exchange_rate_histories
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Exchange rate histories table
        if (! Schema::hasTable('exchange_rate_histories')) {
            Schema::create('exchange_rate_histories', function (Blueprint $table) {
                $table->id();
                $table->string('currency_code', 3);
                $table->decimal('rate_buy', 18, 6);
                $table->decimal('rate_sell', 18, 6);
                $table->string('source', 50);
                $table->timestamp('fetched_at');
                $table->timestamp('created_at')->useCurrent();
                $table->index(['currency_code', 'fetched_at']);
                $table->foreign('currency_code')->references('code')->on('currencies');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rate_histories');
    }
};
