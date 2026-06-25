<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Add journal_entry_id first if it doesn't exist
            if (! Schema::hasColumn('transactions', 'journal_entry_id')) {
                $table->foreignId('journal_entry_id')
                    ->nullable()
                    ->after('reversal_reason')
                    ->constrained('journal_entries')
                    ->nullOnDelete();
            }

            // Add deferred_journal_entry_id for tracking deferred entries
            if (! Schema::hasColumn('transactions', 'deferred_journal_entry_id')) {
                $table->foreignId('deferred_journal_entry_id')
                    ->nullable()
                    ->after('journal_entry_id')
                    ->constrained('journal_entries')
                    ->nullOnDelete();
            }

            // Add timestamp for tracking when entries were created
            if (! Schema::hasColumn('transactions', 'journal_entries_created_at')) {
                $table->timestamp('journal_entries_created_at')
                    ->nullable()
                    ->after('deferred_journal_entry_id')
                    ->comment('When journal entries were actually created (for deferred Enhanced CDD)');
            }

            // Add has_deferred_accounting boolean flag
            if (! Schema::hasColumn('transactions', 'has_deferred_accounting')) {
                $table->boolean('has_deferred_accounting')
                    ->default(false)
                    ->after('journal_entries_created_at')
                    ->comment('Flag indicating deferred accounting entries were created');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'has_deferred_accounting')) {
                $table->dropColumn('has_deferred_accounting');
            }
            if (Schema::hasColumn('transactions', 'journal_entries_created_at')) {
                $table->dropColumn('journal_entries_created_at');
            }
            if (Schema::hasColumn('transactions', 'deferred_journal_entry_id')) {
                $table->dropForeign(['deferred_journal_entry_id']);
                $table->dropColumn('deferred_journal_entry_id');
            }
        });
    }
};
