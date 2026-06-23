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
            DB::statement('ALTER TABLE transaction_imports RENAME TO _transaction_imports_old');
            DB::statement('
                CREATE TABLE transaction_imports (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    imported_by INTEGER NOT NULL,
                    filename TEXT NOT NULL,
                    original_filename TEXT NOT NULL,
                    total_rows INTEGER NOT NULL,
                    success_count INTEGER NOT NULL DEFAULT 0,
                    error_count INTEGER NOT NULL DEFAULT 0,
                    error_details TEXT NULL,
                    status TEXT NOT NULL,
                    imported_at TIMESTAMP NULL,
                    completed_at TIMESTAMP NULL,
                    created_at TIMESTAMP NULL,
                    updated_at TIMESTAMP NULL,
                    file_hash TEXT NULL,
                    file_size INTEGER NULL,
                    processed_rows INTEGER NOT NULL DEFAULT 0
                )
            ');
            DB::statement('
                INSERT INTO transaction_imports (id, imported_by, filename, original_filename, total_rows, success_count, error_count, error_details, status, imported_at, completed_at, created_at, updated_at)
                SELECT id, user_id, filename, original_filename, total_rows, success_count, error_count, errors, status, started_at, completed_at, created_at, updated_at
                FROM _transaction_imports_old
            ');
            DB::statement('DROP TABLE _transaction_imports_old');
        } else {
            Schema::table('transaction_imports', function (Blueprint $table) {
                $table->renameColumn('user_id', 'imported_by');
                $table->renameColumn('errors', 'error_details');
                $table->renameColumn('started_at', 'imported_at');
                $table->string('file_hash')->nullable()->after('original_filename');
                $table->unsignedBigInteger('file_size')->nullable()->after('file_hash');
                $table->unsignedInteger('processed_rows')->default(0)->after('total_rows');
            });
        }
    }

    public function down(): void
    {
        $driver = config('database.connections.'.config('database.default').'.driver');

        if ($driver === 'sqlite') {
            DB::statement('ALTER TABLE transaction_imports RENAME TO _transaction_imports_old');
            DB::statement('
                CREATE TABLE transaction_imports (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    filename TEXT NOT NULL,
                    original_filename TEXT NOT NULL,
                    total_rows INTEGER NOT NULL,
                    success_count INTEGER NOT NULL DEFAULT 0,
                    error_count INTEGER NOT NULL DEFAULT 0,
                    errors TEXT NULL,
                    status TEXT NOT NULL,
                    started_at TIMESTAMP NULL,
                    completed_at TIMESTAMP NULL,
                    created_at TIMESTAMP NULL,
                    updated_at TIMESTAMP NULL
                )
            ');
            DB::statement('
                INSERT INTO transaction_imports (id, user_id, filename, original_filename, total_rows, success_count, error_count, errors, status, started_at, completed_at, created_at, updated_at)
                SELECT id, user_id, filename, original_filename, total_rows, success_count, error_count, errors, status, started_at, completed_at, created_at, updated_at
                FROM _transaction_imports_old
            ');
            DB::statement('DROP TABLE _transaction_imports_old');
        } else {
            Schema::table('transaction_imports', function (Blueprint $table) {
                $table->dropColumn(['file_hash', 'file_size', 'processed_rows']);
                $table->renameColumn('imported_at', 'started_at');
                $table->renameColumn('error_details', 'errors');
                $table->renameColumn('imported_by', 'user_id');
            });
        }
    }
};
