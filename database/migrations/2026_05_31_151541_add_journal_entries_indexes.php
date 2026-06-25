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
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->index('entry_date', 'idx_journal_entries_entry_date');
            $table->index('period_id', 'idx_journal_entries_period_id');
            $table->index('status', 'idx_journal_entries_status');
            $table->index('created_by', 'idx_journal_entries_created_by');
            $table->index(['period_id', 'status'], 'idx_journal_entries_period_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropIndex('idx_journal_entries_entry_date');
            $table->dropIndex('idx_journal_entries_period_id');
            $table->dropIndex('idx_journal_entries_status');
            $table->dropIndex('idx_journal_entries_created_by');
            $table->dropIndex('idx_journal_entries_period_status');
        });
    }
};
