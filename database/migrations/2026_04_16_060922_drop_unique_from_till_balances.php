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
        Schema::table('till_balances', function (Blueprint $table) {
            // Drop the unique constraint on (till_id, date, currency_code)
            // This constraint prevented multiple rows for same till/date/currency,
            // which is needed for handover (closed row + new open row for same day).
            // Application logic already prevents multiple *open* balances via lock checks.
            $table->dropUnique(['till_id', 'date', 'currency_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('till_balances', function (Blueprint $table) {
            // Restore uniqueness to ensure only one open balance per till/date/currency
            $table->unique(['till_id', 'date', 'currency_code']);
        });
    }
};
