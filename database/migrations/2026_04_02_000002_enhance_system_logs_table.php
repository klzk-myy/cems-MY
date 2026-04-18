<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_logs', function (Blueprint $table) {
            // Add severity column
            if (! Schema::hasColumn('system_logs', 'severity')) {
                $table->enum('severity', ['INFO', 'WARNING', 'ERROR', 'CRITICAL'])
                    ->default('INFO')
                    ->after('action');
            }

            // Add session_id column
            if (! Schema::hasColumn('system_logs', 'session_id')) {
                $table->string('session_id', 255)->nullable()->after('user_agent');
            }

            // Add session_id index
            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::table('system_logs', function (Blueprint $table) {
            // Drop columns
            $table->dropColumn(['severity', 'session_id']);

            // Drop indexes
            $table->dropIndex(['ip_address']);
            $table->dropIndex('system_logs_user_agent_index');
            $table->dropIndex(['session_id']);
        });
    }
};
