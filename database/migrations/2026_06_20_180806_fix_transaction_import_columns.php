<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_imports', function (Blueprint $table) {
            $table->renameColumn('user_id', 'imported_by');
            $table->renameColumn('errors', 'error_details');
            $table->renameColumn('started_at', 'imported_at');
            $table->string('file_hash')->nullable()->after('original_filename');
            $table->unsignedBigInteger('file_size')->nullable()->after('file_hash');
            $table->unsignedInteger('processed_rows')->default(0)->after('total_rows');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_imports', function (Blueprint $table) {
            $table->dropColumn(['file_hash', 'file_size', 'processed_rows']);
            $table->renameColumn('imported_at', 'started_at');
            $table->renameColumn('error_details', 'errors');
            $table->renameColumn('imported_by', 'user_id');
        });
    }
};
