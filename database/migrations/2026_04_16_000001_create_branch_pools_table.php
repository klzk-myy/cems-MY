<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_pools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->string('currency_code', 3);
            $table->decimal('available_balance', 20, 4)->default(0);
            $table->decimal('allocated_balance', 20, 4)->default(0);
            $table->decimal('total_balance', 20, 4)->virtualAs('available_balance + allocated_balance');
            $table->timestamps();

            $table->unique(['branch_id', 'currency_code']);
            $table->index(['branch_id', 'currency_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_pools');
    }
};
