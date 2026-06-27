<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Prevents duplicate confirmation records per transaction. The application
     * logic only creates Pending/Confirmed confirmations, so this unique index
     * ensures only one such confirmation exists per transaction at the DB level.
     * Concurrent requests are serialised via row-level locking in the service.
     */
    public function up(): void
    {
        Schema::table('transaction_confirmations', function (Blueprint $table) {
            $table->unique('transaction_id', 'transaction_confirmations_transaction_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaction_confirmations', function (Blueprint $table) {
            $table->dropUnique('transaction_confirmations_transaction_id_unique');
        });
    }
};
