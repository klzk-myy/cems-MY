<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add source_of_wealth column to transactions table.
     *
     * Per pd-00.md 14C.13.1(c): PEPs must provide BOTH source of wealth AND source of funds.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('source_of_wealth', 255)->nullable()->after('source_of_funds');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('source_of_wealth');
        });
    }
};
