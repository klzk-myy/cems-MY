<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->string('account_code', 20); // Cash account being reconciled
            $table->foreign('account_code')->references('account_code')->on('chart_of_accounts');
            $table->date('statement_date');
            $table->string('reference', 50)->nullable(); // Statement reference
            $table->text('description');
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->enum('status', ['unmatched', 'matched', 'exception'])->default('unmatched');
            $table->foreignId('matched_to_journal_entry_id')->nullable()->constrained('journal_entries');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('matched_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['account_code', 'statement_date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliations');
    }
};
