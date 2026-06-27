<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Prevents duplicate confirmation records per transaction. Rejected confirmations
     * are deleted by the service, so the unique index on transaction_id protects
     * against concurrent pending/confirmed rows while still allowing a new request
     * after a rejection.
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
