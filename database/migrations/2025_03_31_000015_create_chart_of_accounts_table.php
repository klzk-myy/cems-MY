<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->string('account_code', 20)->primary();
            $table->string('account_name', 255);
            $table->enum('account_type', ['Asset', 'Liability', 'Equity', 'Revenue', 'Expense', 'Off-Balance']);
            $table->string('parent_code', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreign('parent_code')->references('account_code')->on('chart_of_accounts');
            $table->index('account_type');
        });

        // Insert base accounts
        DB::table('chart_of_accounts')->insert([
            ['account_code' => '1000', 'account_name' => 'Cash - MYR', 'account_type' => 'Asset'],
            ['account_code' => '1100', 'account_name' => 'Cash - USD', 'account_type' => 'Asset'],
            ['account_code' => '1200', 'account_name' => 'Cash - EUR', 'account_type' => 'Asset'],
            ['account_code' => '2000', 'account_name' => 'Foreign Currency Inventory', 'account_type' => 'Asset'],
            ['account_code' => '4000', 'account_name' => 'Revenue - Forex', 'account_type' => 'Revenue'],
            ['account_code' => '5000', 'account_name' => 'Revenue - Forex Trading', 'account_type' => 'Revenue'],
            ['account_code' => '5100', 'account_name' => 'Revenue - Revaluation Gain', 'account_type' => 'Revenue'],
            ['account_code' => '6000', 'account_name' => 'Expense - Forex Loss', 'account_type' => 'Expense'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('chart_of_accounts');
    }
};
