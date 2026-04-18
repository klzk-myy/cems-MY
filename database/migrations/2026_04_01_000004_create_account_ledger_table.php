<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_ledger', function (Blueprint $table) {
            $table->id();
            $table->string('account_code', 20)->nullable(false);
            $table->date('entry_date')->nullable(false);
            $table->foreignId('journal_entry_id')->nullable(false)->constrained('journal_entries');
            $table->decimal('debit', 18, 4)->default(0);
            $table->decimal('credit', 18, 4)->default(0);
            $table->decimal('running_balance', 18, 4)->nullable(false);
            $table->timestamps();

            $table->foreign('account_code')->references('account_code')->on('chart_of_accounts');
            $table->index(['account_code', 'entry_date']);
            $table->index('journal_entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_ledger');
    }
};
