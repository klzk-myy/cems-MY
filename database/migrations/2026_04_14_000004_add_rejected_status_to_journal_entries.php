<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add 'Rejected' status to journal_entries and change default to 'Draft'.
     *
     * This migration:
     * 1. Adds 'Rejected' as a valid status value for journal entries
     * 2. Changes the default status from 'Posted' to 'Draft'
     *
     * Note: MySQL enum modification requires rebuilding the column.
     */
    public function up(): void
    {
        // For MySQL, we need to modify the enum - use raw DB statement
        // First, update any entries with null or invalid status to 'Draft'
        DB::statement("UPDATE journal_entries SET status = 'Draft' WHERE status IS NULL");

        // Modify the enum column to include 'Rejected' and change default
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE journal_entries MODIFY COLUMN status ENUM('Draft', 'Pending', 'Posted', 'Reversed', 'Rejected') DEFAULT 'Draft'");
        }
    }

    /**
     * Reverse the changes.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE journal_entries MODIFY COLUMN status ENUM('Draft', 'Pending', 'Posted', 'Reversed') DEFAULT 'Posted'");
        }
    }
};
