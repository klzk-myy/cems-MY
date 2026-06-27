<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Production safety: if duplicate (branch_id, currency_code) rows exist,
        // keep the most recently updated row and delete the rest before adding
        // the unique constraint.
        $duplicates = DB::table('exchange_rates')
            ->selectRaw('branch_id, currency_code, MAX(updated_at) as max_updated_at, COUNT(*) as count')
            ->groupBy('branch_id', 'currency_code')
            ->having('count', '>', 1)
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('exchange_rates')
                ->where('branch_id', $duplicate->branch_id)
                ->where('currency_code', $duplicate->currency_code)
                ->whereRaw('COALESCE(updated_at, created_at) < ?', [$duplicate->max_updated_at])
                ->delete();
        }

        Schema::table('exchange_rates', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'currency_code']);
            $table->unique(['branch_id', 'currency_code'], 'exchange_rates_branch_currency_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exchange_rates', function (Blueprint $table) {
            $table->dropUnique('exchange_rates_branch_currency_unique');
            $table->index(['branch_id', 'currency_code']);
        });
    }
};
