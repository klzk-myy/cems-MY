<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customer_risk_history', 'previous_score')) {
            return;
        }

        $driver = config('database.connections.'.config('database.default').'.driver');

        if ($driver === 'sqlite') {
            DB::statement('ALTER TABLE customer_risk_history RENAME TO _customer_risk_history_old');
            DB::statement('
                CREATE TABLE customer_risk_history (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    customer_id INTEGER NOT NULL,
                    old_score INTEGER NULL,
                    new_score INTEGER NOT NULL,
                    old_rating TEXT NULL,
                    new_rating TEXT NOT NULL,
                    change_reason TEXT NOT NULL,
                    assessed_by INTEGER NULL,
                    created_at TIMESTAMP NULL,
                    updated_at TIMESTAMP NULL
                )
            ');
            DB::statement('
                INSERT INTO customer_risk_history (id, customer_id, old_score, new_score, old_rating, new_rating, change_reason, assessed_by, created_at, updated_at)
                SELECT id, customer_id, previous_score, new_score, previous_rating, new_rating, change_reason, changed_by, created_at, updated_at
                FROM _customer_risk_history_old
            ');
            DB::statement('DROP TABLE _customer_risk_history_old');
        } else {
            Schema::table('customer_risk_history', function (Blueprint $table) {
                $table->renameColumn('previous_score', 'old_score');
                $table->renameColumn('previous_rating', 'old_rating');
                $table->renameColumn('changed_by', 'assessed_by');
                $table->dropColumn('changed_at');
            });
        }
    }

    public function down(): void
    {
        // Intentionally empty. This forward-fix migration should never reverse
        // a canonical schema. Rolling it back simply removes the migration record.
    }
};
