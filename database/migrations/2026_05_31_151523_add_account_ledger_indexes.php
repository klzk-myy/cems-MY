<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('account_ledger', function (Blueprint $table) {
            $table->index(['account_code', 'entry_date'], 'idx_account_ledger_account_entry');
            $table->index('entry_date', 'idx_account_ledger_entry_date');
            $table->index('journal_entry_id', 'idx_account_ledger_journal_entry');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account_ledger', function (Blueprint $table) {
            $table->dropIndex('idx_account_ledger_account_entry');
            $table->dropIndex('idx_account_ledger_entry_date');
            $table->dropIndex('idx_account_ledger_journal_entry');
        });
    }
};
