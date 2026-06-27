<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            // Merge duplicates: keep the row with the highest id (most recent)
            $duplicates = DB::table('exchange_rates')
                ->selectRaw('branch_id, currency_code, MAX(id) as max_id, COUNT(*) as count')
                ->groupBy('branch_id', 'currency_code')
                ->having('count', '>', 1)
                ->get();

            foreach ($duplicates as $duplicate) {
                DB::table('exchange_rates')
                    ->where('branch_id', $duplicate->branch_id)
                    ->where('currency_code', $duplicate->currency_code)
                    ->where('id', '!=', $duplicate->max_id)
                    ->delete();
            }

            Schema::table('exchange_rates', function (Blueprint $table) {
                $table->dropIndex(['branch_id', 'currency_code']);
                $table->unique(['branch_id', 'currency_code'], 'exchange_rates_branch_currency_unique');
            });
        });
    }

    public function down(): void
    {
        Schema::table('exchange_rates', function (Blueprint $table) {
            $table->dropUnique('exchange_rates_branch_currency_unique');
            $table->index(['branch_id', 'currency_code']);
        });
    }
};
