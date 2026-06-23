<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_daily_rates', function (Blueprint $table) {
            $table->id();
            $table->date('rate_date');
            $table->string('currency_code', 3);
            $table->decimal('buy_rate', 10, 6);
            $table->decimal('sell_rate', 10, 6);
            $table->decimal('mid_rate', 10, 6);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->unique(['rate_date', 'currency_code']);
            $table->index(['rate_date', 'currency_code']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_daily_rates');
    }
};
