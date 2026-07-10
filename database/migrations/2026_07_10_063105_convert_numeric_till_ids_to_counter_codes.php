<?php

use App\Models\Counter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Converts legacy numeric till_id values to the corresponding Counter::code.
     * Non-numeric values are assumed to already be counter codes and are left
     * unchanged. Values that reference a missing counter are logged as warnings
     * and left in place because all till_id columns are non-nullable.
     */
    public function up(): void
    {
        $tables = [
            'till_balances',
            'transactions',
            'stock_reservations',
            'revaluation_entries',
        ];

        DB::transaction(function () use ($tables): void {
            foreach ($tables as $table) {
                $numericTillIds = DB::table($table)
                    ->distinct()
                    ->pluck('till_id')
                    ->filter(fn (?string $value): bool => $value !== null && preg_match('/^\d+$/', $value) === 1)
                    ->unique()
                    ->values();

                foreach ($numericTillIds as $numericTillId) {
                    $counter = Counter::withTrashed()->find($numericTillId);

                    if ($counter === null) {
                        Log::warning("Cannot convert numeric till_id [{$numericTillId}] on table [{$table}]: counter not found.");

                        continue;
                    }

                    DB::table($table)
                        ->where('till_id', $numericTillId)
                        ->update(['till_id' => $counter->code]);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * The conversion is not safely reversible: counter codes are not guaranteed
     * to map back to the original numeric IDs once other migrations or data
     * changes may have occurred. A manual restore from backup should be used if
     * the legacy numeric values are required again.
     */
    public function down(): void
    {
        // Not reversible without risking data loss.
    }
};
