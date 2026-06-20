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
            $table->string('branch_id', 50)->default('HQ');
            $table->decimal('quantity', 18, 4)->default(0);
            $table->decimal('average_cost', 18, 6)->default(0);
            $table->decimal('total_cost', 18, 4)->default(0);
            $table->decimal('current_rate', 18, 6)->default(0);
            $table->decimal('current_value', 18, 4)->default(0);
            $table->decimal('unrealized_gain_loss', 18, 4)->default(0);
            $table->timestamp('last_revalued_at')->nullable();
            $table->timestamps();
            $table->unique(['currency_code', 'branch_id']);
            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->index('currency_code');
            $table->index('branch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_positions');
    }
};
