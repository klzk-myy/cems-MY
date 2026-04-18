<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('currency_code', 3);
            $table->decimal('rate_buy', 18, 6);
            $table->decimal('rate_sell', 18, 6);
            $table->string('source', 50);
            $table->timestamp('fetched_at');
            $table->timestamps();
            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->index(['currency_code', 'fetched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
