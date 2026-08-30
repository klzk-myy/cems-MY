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
            $table->index(['branch_id', 'created_at'], 'transactions_branch_created');
        });

        Schema::table('flagged_transactions', function (Blueprint $table) {
            $table->index(['status', 'flag_type'], 'flagged_transactions_status_flag_type');
        });

        Schema::table('alerts', function (Blueprint $table) {
            $table->index(['priority', 'status'], 'alerts_priority_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_branch_created');
        });

        Schema::table('flagged_transactions', function (Blueprint $table) {
            $table->dropIndex('flagged_transactions_status_flag_type');
        });

        Schema::table('alerts', function (Blueprint $table) {
            $table->dropIndex('alerts_priority_status');
        });
    }
};
