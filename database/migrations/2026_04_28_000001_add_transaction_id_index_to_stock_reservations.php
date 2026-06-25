<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_reservations', function (Blueprint $table) {
            if (! $this->hasIndex('stock_reservations', 'stock_reservations_transaction_id_index')) {
                $table->index('transaction_id', 'stock_reservations_transaction_id_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_reservations', function (Blueprint $table) {
            $table->dropIndex('stock_reservations_transaction_id_index');
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        $indexes = Schema::getIndexes($table);
        foreach ($indexes as $idx) {
            if ($idx['name'] === $index) {
                return true;
            }
        }

        return false;
    }
};
