<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE counter_sessions MODIFY COLUMN status ENUM('open', 'closed', 'handed_over', 'pending_handover', 'emergency_closed') DEFAULT 'open'");
        } else {
            Schema::table('counter_sessions', function (Blueprint $table) {
                $table->string('status')->default('open')->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE counter_sessions MODIFY COLUMN status ENUM('open', 'closed', 'handed_over', 'pending_handover') DEFAULT 'open'");
        } else {
            Schema::table('counter_sessions', function (Blueprint $table) {
                $table->string('status')->default('open')->change();
            });
        }
    }
};
