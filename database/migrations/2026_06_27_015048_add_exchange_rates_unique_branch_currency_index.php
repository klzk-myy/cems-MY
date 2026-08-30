<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Removed nested DB::transaction to avoid SQLite PDO conflicts
        // Laravel migration framework manages transactions automatically.
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

        $driver = DB::getDriverName();

        Schema::table('exchange_rates', function (Blueprint $table) use ($driver) {
            // On MySQL the composite (branch_id, currency_code) index backs the
            // branch_id foreign key, so InnoDB refuses to drop it (error 1553).
            // Drop and re-create the FK around the index swap; SQLite needs no
            // such dance and does not support ALTER DROP CONSTRAINT.
            if ($driver === 'mysql') {
                $table->dropForeign(['branch_id']);
            }

            $table->dropIndex(['branch_id', 'currency_code']);
            $table->unique(['branch_id', 'currency_code'], 'exchange_rates_branch_currency_unique');

            if ($driver === 'mysql') {
                $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        Schema::table('exchange_rates', function (Blueprint $table) use ($driver) {
            if ($driver === 'mysql') {
                $table->dropForeign(['branch_id']);
            }

            $table->dropUnique('exchange_rates_branch_currency_unique');
            $table->index(['branch_id', 'currency_code']);

            if ($driver === 'mysql') {
                $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            }
        });
    }
};
