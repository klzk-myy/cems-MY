<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exchange_rate_histories', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('currency_code')->constrained()->nullOnDelete();
            $table->index(['branch_id', 'currency_code', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::table('exchange_rate_histories', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropIndex(['branch_id', 'currency_code', 'effective_date']);
            $table->dropColumn('branch_id');
        });
    }
};
