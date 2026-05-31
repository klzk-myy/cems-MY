<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->string('normal_balance', 6)->nullable()->after('account_type')
                ->comment('Debit or Credit');
        });

        DB::table('chart_of_accounts')
            ->whereIn('account_type', ['Asset', 'Expense'])
            ->whereNull('normal_balance')
            ->update(['normal_balance' => 'Debit']);

        DB::table('chart_of_accounts')
            ->whereIn('account_type', ['Liability', 'Equity', 'Revenue'])
            ->whereNull('normal_balance')
            ->update(['normal_balance' => 'Credit']);
    }

    public function down(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->dropColumn('normal_balance');
        });
    }
};
