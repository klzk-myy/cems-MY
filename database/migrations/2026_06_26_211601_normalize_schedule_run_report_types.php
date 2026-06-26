<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('report_schedules')
            ->where('report_type', 'position_limit')
            ->update(['report_type' => 'plr']);

        DB::table('report_runs')
            ->where('report_type', 'position_limit')
            ->update(['report_type' => 'plr']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('report_schedules')
            ->where('report_type', 'plr')
            ->update(['report_type' => 'position_limit']);

        DB::table('report_runs')
            ->where('report_type', 'plr')
            ->update(['report_type' => 'position_limit']);
    }
};
