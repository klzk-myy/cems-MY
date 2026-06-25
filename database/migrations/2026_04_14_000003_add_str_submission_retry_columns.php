<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('str_reports', function (Blueprint $table) {
            $table->unsignedInteger('retry_count')->default(0)->after('filing_deadline');
            $table->text('last_error')->nullable()->after('retry_count');
            $table->timestamp('last_retry_at')->nullable()->after('last_error');
        });
    }

    public function down(): void
    {
        Schema::table('str_reports', function (Blueprint $table) {
            $table->dropColumn(['retry_count', 'last_error', 'last_retry_at']);
        });
    }
};
