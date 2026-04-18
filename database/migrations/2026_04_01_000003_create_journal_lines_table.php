<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->nullable(false)->constrained('journal_entries');
            $table->string('account_code', 20)->nullable(false);
            $table->decimal('debit', 18, 4)->default(0);
            $table->decimal('credit', 18, 4)->default(0);
            $table->string('description', 255)->nullable();
            $table->timestamps();

            $table->foreign('account_code')->references('account_code')->on('chart_of_accounts');
            $table->index('journal_entry_id');
            $table->index('account_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
    }
};
