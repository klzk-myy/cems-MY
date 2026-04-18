<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            // Only add entry_number if it doesn't exist (status column already exists)
            if (! Schema::hasColumn('journal_entries', 'entry_number')) {
                $table->string('entry_number', 20)->unique()->after('id')->nullable();
            }
            // Add workflow columns if they don't exist
            if (! Schema::hasColumn('journal_entries', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('users');
            }
            if (! Schema::hasColumn('journal_entries', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->constrained('users');
            }
            if (! Schema::hasColumn('journal_entries', 'approved_at')) {
                $table->timestamp('approved_at')->nullable();
            }
            if (! Schema::hasColumn('journal_entries', 'approval_notes')) {
                $table->text('approval_notes')->nullable();
            }
            if (! Schema::hasColumn('journal_entries', 'cost_center_id')) {
                $table->foreignId('cost_center_id')->nullable();
            }
            if (! Schema::hasColumn('journal_entries', 'department_id')) {
                $table->foreignId('department_id')->nullable();
            }
            if (! Schema::hasColumn('journal_entries', 'entry_number')) {
                $table->index('entry_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $columnsToDrop = array_filter([
                'entry_number',
                'created_by',
                'approved_by',
                'approved_at',
                'approval_notes',
                'cost_center_id',
                'department_id',
            ], fn ($col) => Schema::hasColumn('journal_entries', $col));

            if (! empty($columnsToDrop)) {
                $table->dropForeign(['created_by']);
                $table->dropForeign(['approved_by']);
                $table->dropForeign(['cost_center_id']);
                $table->dropForeign(['department_id']);
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
