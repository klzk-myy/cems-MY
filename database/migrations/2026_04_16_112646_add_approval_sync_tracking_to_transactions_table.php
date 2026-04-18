<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->boolean('approval_sync_failed')->default(false)->after('transition_history');
            $table->timestamp('approval_sync_failed_at')->nullable()->after('approval_sync_failed');
            $table->text('approval_sync_error')->nullable()->after('approval_sync_failed_at');
            $table->index('approval_sync_failed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('approval_sync_failed');
            $table->dropColumn(['approval_sync_failed', 'approval_sync_failed_at', 'approval_sync_error']);
        });
    }
};
