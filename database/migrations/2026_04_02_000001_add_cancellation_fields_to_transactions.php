<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL - modify enum and add columns
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE transactions MODIFY COLUMN status ENUM('Pending', 'Completed', 'OnHold', 'Rejected', 'Reversed', 'Cancelled') DEFAULT 'Pending'");
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('approved_at');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users');
            $table->text('cancellation_reason')->nullable()->after('cancelled_by');
            $table->foreignId('original_transaction_id')->nullable()->after('cancellation_reason')->constrained('transactions');
            $table->boolean('is_refund')->default(false)->after('original_transaction_id');

            // Add index for cancelled transactions lookup
            $table->index('cancelled_at');
            $table->index('is_refund');
            $table->index('original_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['cancelled_by']);
            $table->dropForeign(['original_transaction_id']);
            $table->dropColumn([
                'cancelled_at',
                'cancelled_by',
                'cancellation_reason',
                'original_transaction_id',
                'is_refund',
            ]);
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE transactions MODIFY COLUMN status ENUM('Pending', 'Completed', 'OnHold', 'Rejected', 'Reversed') DEFAULT 'Pending'");
        }
    }
};
