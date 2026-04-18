<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('till_balances', function (Blueprint $table) {
            $table->id();
            $table->string('till_id', 50);
            $table->string('currency_code', 3);
            $table->decimal('opening_balance', 18, 4);
            $table->decimal('closing_balance', 18, 4)->nullable();
            $table->decimal('variance', 18, 4)->nullable();
            $table->date('date');
            $table->foreignId('opened_by')->constrained('users');
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->unique(['till_id', 'date', 'currency_code']);
            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('till_balances');
    }
};
