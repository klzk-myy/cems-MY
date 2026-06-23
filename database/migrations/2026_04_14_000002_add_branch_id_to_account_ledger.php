<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add branch_id to account_ledger for branch-scoped ledger queries.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('account_ledger', 'branch_id')) {
            Schema::table('account_ledger', function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->after('journal_entry_id')->constrained('branches')->nullOnDelete();
                $table->index('branch_id');
            });
        }
    }

    /**
     * Remove branch_id from account_ledger.
     */
    public function down(): void
    {
        if (Schema::hasColumn('account_ledger', 'branch_id')) {
            Schema::table('account_ledger', function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            });
        }
    }
};
