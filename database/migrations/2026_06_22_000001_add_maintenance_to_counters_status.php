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

        DB::statement("ALTER TABLE counters MODIFY COLUMN status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active'");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE counters MODIFY COLUMN status ENUM('active', 'inactive') DEFAULT 'active'");
    }
};
