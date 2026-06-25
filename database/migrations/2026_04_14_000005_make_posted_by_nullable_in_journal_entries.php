<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Make posted_by and posted_at nullable in journal_entries.
     *
     * This is needed because entries are now created in Draft status
     * and posted_by/posted_at are only set when the entry is approved and posted.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE journal_entries MODIFY COLUMN posted_by BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE journal_entries MODIFY COLUMN posted_at TIMESTAMP NULL');
        } elseif (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('CREATE TEMPORARY TABLE __journal_entries_backup AS SELECT * FROM journal_entries');
            DB::statement('PRAGMA foreign_keys=OFF');
            DB::statement('DROP TABLE journal_entries');
            DB::statement('CREATE TABLE journal_entries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                entry_date DATE NOT NULL,
                reference_type VARCHAR(50) NOT NULL,
                reference_id BIGINT UNSIGNED NULL,
                description TEXT NOT NULL,
                status VARCHAR(20) DEFAULT "Posted",
                posted_by BIGINT UNSIGNED NULL,
                posted_at TIMESTAMP NULL,
                reversed_by BIGINT UNSIGNED NULL,
                reversed_at TIMESTAMP NULL,
                created_by BIGINT UNSIGNED NULL,
                approved_by BIGINT UNSIGNED NULL,
                approved_at TIMESTAMP NULL,
                approval_notes TEXT NULL,
                entry_number VARCHAR(50) NULL,
                cost_center_id BIGINT UNSIGNED NULL,
                department_id BIGINT UNSIGNED NULL,
                branch_id BIGINT UNSIGNED NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                period_id BIGINT UNSIGNED NULL,
                FOREIGN KEY (posted_by) REFERENCES users(id),
                FOREIGN KEY (reversed_by) REFERENCES users(id),
                FOREIGN KEY (created_by) REFERENCES users(id),
                FOREIGN KEY (approved_by) REFERENCES users(id),
                FOREIGN KEY (branch_id) REFERENCES branches(id),
                FOREIGN KEY (period_id) REFERENCES accounting_periods(id)
            )');
            DB::statement('INSERT INTO journal_entries SELECT * FROM __journal_entries_backup');
            DB::statement('DROP TABLE __journal_entries_backup');
            DB::statement('PRAGMA foreign_keys=ON');
        }
    }

    /**
     * Reverse the changes.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE journal_entries MODIFY COLUMN posted_by BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE journal_entries MODIFY COLUMN posted_at TIMESTAMP NOT NULL');
        }
    }
};
