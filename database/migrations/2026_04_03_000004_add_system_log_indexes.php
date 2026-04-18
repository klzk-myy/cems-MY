<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_logs', function (Blueprint $table) {
            // Add indexes for common query patterns
            $table->index('action', 'idx_system_logs_action');
            $table->index('severity', 'idx_system_logs_severity');
            $table->index('entity_type', 'idx_system_logs_entity_type');
            $table->index(['user_id', 'created_at'], 'idx_system_logs_user_date');
            $table->index(['action', 'created_at'], 'idx_system_logs_action_date');
            $table->index(['severity', 'created_at'], 'idx_system_logs_severity_date');
        });
    }

    public function down(): void
    {
        Schema::table('system_logs', function (Blueprint $table) {
            $table->dropIndex('idx_system_logs_action');
            $table->dropIndex('idx_system_logs_severity');
            $table->dropIndex('idx_system_logs_entity_type');
            $table->dropIndex('idx_system_logs_user_date');
            $table->dropIndex('idx_system_logs_action_date');
            $table->dropIndex('idx_system_logs_severity_date');
        });
    }
};
