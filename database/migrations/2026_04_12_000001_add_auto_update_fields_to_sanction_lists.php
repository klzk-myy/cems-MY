<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Check if columns already exist (in case of partial migration)
        $columns = Schema::getColumnListing('sanction_lists');

        Schema::table('sanction_lists', function (Blueprint $table) use ($columns) {
            // Source URL for auto-download
            if (! in_array('source_url', $columns)) {
                $table->string('source_url', 500)->nullable()->after('list_type');
            }

            // Format of the source (XML, CSV, JSON)
            if (! in_array('source_format', $columns)) {
                $table->enum('source_format', ['XML', 'CSV', 'JSON'])->nullable()->after('source_url');
            }

            // Last successful update timestamp
            if (! in_array('last_updated_at', $columns)) {
                $table->timestamp('last_updated_at')->nullable()->after('uploaded_at');
            }

            // Last attempted update (for tracking failures)
            if (! in_array('last_attempted_at', $columns)) {
                $table->timestamp('last_attempted_at')->nullable()->after('last_updated_at');
            }

            // Status of last update attempt
            if (! in_array('update_status', $columns)) {
                $table->enum('update_status', ['success', 'failed', 'pending', 'never_run'])->default('never_run')->after('last_attempted_at');
            }

            // Error message if failed
            if (! in_array('last_error_message', $columns)) {
                $table->text('last_error_message')->nullable()->after('update_status');
            }

            // Entry count for change detection
            if (! in_array('entry_count', $columns)) {
                $table->unsignedInteger('entry_count')->default(0)->after('last_error_message');
            }

            // Checksum of last downloaded file for change detection
            if (! in_array('last_checksum', $columns)) {
                $table->string('last_checksum', 64)->nullable()->after('entry_count');
            }

            // System user ID for automated updates (null for manual)
            if (! in_array('auto_updated_by', $columns)) {
                $table->foreignId('auto_updated_by')->nullable()->constrained('users');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sanction_lists', function (Blueprint $table) {
            if (Schema::hasColumn('sanction_lists', 'auto_updated_by')) {
                $table->dropForeign(['auto_updated_by']);
                $table->dropColumn('auto_updated_by');
            }

            $columnsToDrop = [
                'source_url',
                'source_format',
                'last_updated_at',
                'last_attempted_at',
                'update_status',
                'last_error_message',
                'entry_count',
                'last_checksum',
            ];

            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('sanction_lists', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
