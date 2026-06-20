<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = config('database.connections.'.config('database.default').'.driver');

        if ($driver === 'sqlite') {
            Schema::table('customer_risk_history', function (Blueprint $table) {
                $table->timestamp('changed_at')->nullable()->after('changed_by');
            });

            DB::statement('ALTER TABLE customer_risk_history RENAME TO _customer_risk_history_old');
            DB::statement('
                CREATE TABLE customer_risk_history (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    customer_id INTEGER NOT NULL,
                    previous_score INTEGER NULL,
                    new_score INTEGER NOT NULL,
                    previous_rating TEXT NULL,
                    new_rating TEXT NOT NULL,
                    change_reason TEXT NOT NULL,
                    changed_by INTEGER NOT NULL,
                    changed_at TIMESTAMP NULL,
                    created_at TIMESTAMP NULL,
                    updated_at TIMESTAMP NULL
                )
            ');
            DB::statement('
                INSERT INTO customer_risk_history (id, customer_id, previous_score, new_score, previous_rating, new_rating, change_reason, changed_by, created_at, updated_at)
                SELECT id, customer_id, old_score, new_score, old_rating, new_rating, change_reason, assessed_by, created_at, updated_at
                FROM _customer_risk_history_old
            ');
            DB::statement('DROP TABLE _customer_risk_history_old');
        } else {
            Schema::table('customer_risk_history', function (Blueprint $table) {
                $table->renameColumn('old_score', 'previous_score');
                $table->renameColumn('old_rating', 'previous_rating');
                $table->renameColumn('assessed_by', 'changed_by');
                $table->timestamp('changed_at')->nullable()->after('changed_by');
            });
        }
    }

    public function down(): void
    {
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
                    assessed_by INTEGER NOT NULL,
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
                $table->dropColumn('changed_at');
                $table->renameColumn('changed_by', 'assessed_by');
                $table->renameColumn('previous_rating', 'old_rating');
                $table->renameColumn('previous_score', 'old_score');
            });
        }
    }
};
