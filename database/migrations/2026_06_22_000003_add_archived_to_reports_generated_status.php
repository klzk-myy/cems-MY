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

        DB::statement("ALTER TABLE reports_generated MODIFY COLUMN status ENUM('Generated', 'Submitted', 'Pending', 'Archived') DEFAULT 'Generated'");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE reports_generated MODIFY COLUMN status ENUM('Generated', 'Submitted', 'Pending') DEFAULT 'Generated'");
    }
};
