<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->string('account_code', 20);
            $table->foreign('account_code')->references('account_code')->on('chart_of_accounts');
            $table->string('period_code', 10);
            $table->decimal('budget_amount', 15, 2);
            $table->decimal('actual_amount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->unique(['account_code', 'period_code']);
            $table->index('period_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
