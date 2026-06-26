<?php

use App\Enums\ReportType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $canonicalValues = array_map(
            fn (ReportType $type): string => $type->value,
            ReportType::cases()
        );

        DB::table('reports_generated')
            ->where('report_type', 'MSB2')
            ->update(['report_type' => ReportType::Msb2->value]);

        DB::table('reports_generated')
            ->where('report_type', 'LMCA')
            ->update(['report_type' => ReportType::Lmca->value]);

        DB::table('reports_generated')
            ->where('report_type', 'QLVR')
            ->update(['report_type' => ReportType::Qlvr->value]);

        DB::table('reports_generated')
            ->where('report_type', 'PLR')
            ->update(['report_type' => ReportType::Plr->value]);

        DB::table('reports_generated')
            ->where('report_type', 'TrialBalance')
            ->update(['report_type' => ReportType::TrialBalance->value]);

        DB::table('reports_generated')
            ->where('report_type', 'MONTH_END')
            ->update(['report_type' => ReportType::MonthEnd->value]);

        DB::table('reports_generated')
            ->where('report_type', 'pl')
            ->update(['report_type' => ReportType::ProfitLoss->value]);

        $remaining = DB::table('reports_generated')
            ->whereNotIn('report_type', $canonicalValues)
            ->distinct()
            ->pluck('report_type');

        if ($remaining->isNotEmpty()) {
            throw new RuntimeException(
                'Unknown report_type values remain after normalization: '.implode(', ', $remaining->toArray())
            );
        }
    }

    public function down(): void
    {
        // Normalization is destructive; no safe reverse mapping exists.
    }
};
