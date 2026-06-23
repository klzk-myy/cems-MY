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
            $table->decimal('buy_total_foreign', 18, 4)->default('0')->after('foreign_total');
            $table->decimal('sell_total_foreign', 18, 4)->default('0')->after('buy_total_foreign');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('till_balances', function (Blueprint $table) {
            $table->dropColumn(['buy_total_foreign', 'sell_total_foreign']);
        });
    }
};
