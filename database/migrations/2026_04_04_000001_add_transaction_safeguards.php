<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Add idempotency key to prevent duplicate transactions
            $table->string('idempotency_key', 100)->nullable()->unique()->after('id');

            // Add version column for optimistic locking
            $table->unsignedInteger('version')->default(0)->after('status');

            // Add index for duplicate detection
            $table->index(['user_id', 'created_at', 'amount_local'], 'idx_duplicate_check');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['idempotency_key', 'version']);
            $table->dropIndex('idx_duplicate_check');
        });
    }
};
