<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL - modify enum to include all statuses (PascalCase)
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE transactions MODIFY COLUMN status ENUM('Draft', 'PendingApproval', 'Approved', 'Processing', 'Completed', 'Finalized', 'Cancelled', 'Reversed', 'Failed', 'Rejected', 'Pending', 'OnHold', 'PendingCancellation') DEFAULT 'Draft'");
        }

        // Add new columns if they don't exist
        if (Schema::hasTable('transactions') && ! Schema::hasColumn('transactions', 'transition_history')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->json('transition_history')->nullable()->after('version');
                $table->string('failure_reason')->nullable()->after('transition_history');
                $table->string('rejection_reason')->nullable()->after('failure_reason');
                $table->string('reversal_reason')->nullable()->after('rejection_reason');
            });
        }
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $columnsToDrop = ['transition_history', 'failure_reason', 'rejection_reason', 'reversal_reason'];
            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('transactions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE transactions MODIFY COLUMN status ENUM('Pending', 'Completed', 'OnHold', 'Rejected', 'Reversed', 'Cancelled') DEFAULT 'Pending'");
        }
    }
};
