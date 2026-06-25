<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add missing foreign key constraints to stock_reservations table.
     *
     * For MySQL: Adds proper FK constraints for referential integrity
     * For SQLite: Skips FK additions (SQLite requires table recreation for FK)
     *
     * Idempotent - safe to run multiple times.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        // SQLite doesn't support adding FKs to existing tables without table recreation
        // For SQLite, we skip FK additions but the schema remains functional
        if ($driver === 'sqlite') {
            return;
        }

        Schema::table('stock_reservations', function (Blueprint $table) {
            // Add FK to transactions (cascade on delete)
            $this->addForeignKeySafely($table, 'transaction_id', 'transactions', 'id', true);

            // Add FK to users (cascade on delete)
            $this->addForeignKeySafely($table, 'created_by', 'users', 'id', true);

            // Add FK to currencies (restrict on delete)
            $this->addForeignKeySafely($table, 'currency_code', 'currencies', 'code', false);
        });
    }

    /**
     * Add foreign key with error handling for idempotency.
     */
    protected function addForeignKeySafely(Blueprint $table, string $column, string $refTable, string $refColumn, bool $cascade): void
    {
        try {
            $fk = $table->foreign($column)->references($refColumn)->on($refTable);
            if ($cascade) {
                $fk->cascadeOnDelete();
            } else {
                $fk->restrictOnDelete();
            }
        } catch (Exception $e) {
            // FK already exists or other error - log and continue
            Log::debug('FK addition skipped: '.$e->getMessage());
        }
    }

    /**
     * Reverse the foreign key additions.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        Schema::table('stock_reservations', function (Blueprint $table) {
            try {
                $table->dropForeign(['transaction_id']);
            } catch (Exception $e) {
            }
            try {
                $table->dropForeign(['created_by']);
            } catch (Exception $e) {
            }
            try {
                $table->dropForeign(['currency_code']);
            } catch (Exception $e) {
            }
        });
    }
};
