<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE flagged_transactions MODIFY COLUMN status ENUM('Open', 'Under_Review', 'Resolved', 'Rejected', 'Escalated') DEFAULT 'Open'");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE flagged_transactions MODIFY COLUMN status ENUM('Open', 'Under_Review', 'Resolved', 'Rejected') DEFAULT 'Open'");
    }
};
