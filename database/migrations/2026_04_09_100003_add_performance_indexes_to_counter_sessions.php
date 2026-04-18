<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add missing indexes to counter_sessions table for production performance.
     */
    public function up(): void
    {
        Schema::table('counter_sessions', function (Blueprint $table) {
            // Counter + date composite (already exists, verify)
            if (! $this->hasIndex('counter_sessions', 'counter_sessions_counter_id_session_date_index')) {
                $table->index(['counter_id', 'session_date'], 'counter_sessions_counter_id_session_date_index');
            }

            // User + date for activity reports
            if (! $this->hasIndex('counter_sessions', 'counter_sessions_user_date_idx')) {
                $table->index(['user_id', 'session_date'], 'counter_sessions_user_date_idx');
            }

            // Status queries
            if (! $this->hasIndex('counter_sessions', 'counter_sessions_status_index')) {
                $table->index('status', 'counter_sessions_status_index');
            }

            // Date range queries
            if (! $this->hasIndex('counter_sessions', 'counter_sessions_session_date_index')) {
                $table->index('session_date', 'counter_sessions_session_date_index');
            }

            // Opened_by lookups
            if (! $this->hasIndex('counter_sessions', 'counter_sessions_opened_by_index')) {
                $table->index('opened_by', 'counter_sessions_opened_by_index');
            }

            // Closed_by lookups
            if (! $this->hasIndex('counter_sessions', 'counter_sessions_closed_by_index')) {
                $table->index('closed_by', 'counter_sessions_closed_by_index');
            }

            // Composite: counter + status for active session lookups
            if (! $this->hasIndex('counter_sessions', 'counter_sessions_counter_status_idx')) {
                $table->index(['counter_id', 'status'], 'counter_sessions_counter_status_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('counter_sessions', function (Blueprint $table) {
            $table->dropIndex('counter_sessions_counter_id_session_date_index');
            $table->dropIndex('counter_sessions_user_date_idx');
            $table->dropIndex('counter_sessions_status_index');
            $table->dropIndex('counter_sessions_session_date_index');
            $table->dropIndex('counter_sessions_opened_by_index');
            $table->dropIndex('counter_sessions_closed_by_index');
            $table->dropIndex('counter_sessions_counter_status_idx');
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
