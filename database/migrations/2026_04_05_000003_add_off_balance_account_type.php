<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Modify the account_type enum to include Off-Balance for MySQL
        if (config('database.default') === 'mysql') {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE chart_of_accounts MODIFY COLUMN account_type ENUM('Asset', 'Liability', 'Equity', 'Revenue', 'Expense', 'Off-Balance')");
        }
    }

    public function down(): void
    {
        if (config('database.default') === 'mysql') {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE chart_of_accounts MODIFY COLUMN account_type ENUM('Asset', 'Liability', 'Equity', 'Revenue', 'Expense')");
        }
    }
};
