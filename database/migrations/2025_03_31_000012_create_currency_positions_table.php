<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currency_positions', function (Blueprint $table) {
            $table->id();
            $table->string('currency_code', 3);
            $table->string('till_id', 50)->default('MAIN');
            $table->decimal('balance', 18, 4)->default(0);
            $table->decimal('avg_cost_rate', 18, 6)->nullable();
            $table->decimal('last_valuation_rate', 18, 6)->nullable();
            $table->decimal('unrealized_pnl', 18, 4)->default(0);
            $table->timestamp('last_valuation_at')->nullable();
            $table->timestamps();
            $table->unique(['currency_code', 'till_id']);
            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->index('currency_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_positions');
    }
};
