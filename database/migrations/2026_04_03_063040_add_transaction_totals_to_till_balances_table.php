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
            $table->decimal('transaction_total', 18, 4)->default(0)->after('variance');
            $table->decimal('foreign_total', 18, 4)->default(0)->after('transaction_total');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('till_balances', function (Blueprint $table) {
            $table->dropColumn(['transaction_total', 'foreign_total']);
        });
    }
};
