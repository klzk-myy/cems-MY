<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('currency_positions', function (Blueprint $table) {
            if (Schema::hasColumn('currency_positions', 'balance')) {
                $table->renameColumn('balance', 'quantity');
            }
            if (Schema::hasColumn('currency_positions', 'avg_cost_rate')) {
                $table->renameColumn('avg_cost_rate', 'average_cost');
            }
            if (Schema::hasColumn('currency_positions', 'last_valuation_rate')) {
                $table->renameColumn('last_valuation_rate', 'current_rate');
            }
            if (Schema::hasColumn('currency_positions', 'unrealized_pnl')) {
                $table->renameColumn('unrealized_pnl', 'unrealized_gain_loss');
            }
            if (Schema::hasColumn('currency_positions', 'last_valuation_at')) {
                $table->renameColumn('last_valuation_at', 'last_revalued_at');
            }
            if (! Schema::hasColumn('currency_positions', 'total_cost')) {
                $table->decimal('total_cost', 18, 4)->default(0)->after('average_cost');
            }
            if (! Schema::hasColumn('currency_positions', 'current_value')) {
                $table->decimal('current_value', 18, 4)->default(0)->after('current_rate');
            }
        });
    }

    public function down(): void
    {
        Schema::table('currency_positions', function (Blueprint $table) {
            if (Schema::hasColumn('currency_positions', 'quantity')) {
                $table->renameColumn('quantity', 'balance');
            }
            if (Schema::hasColumn('currency_positions', 'average_cost')) {
                $table->renameColumn('average_cost', 'avg_cost_rate');
            }
            if (Schema::hasColumn('currency_positions', 'current_rate')) {
                $table->renameColumn('current_rate', 'last_valuation_rate');
            }
            if (Schema::hasColumn('currency_positions', 'unrealized_gain_loss')) {
                $table->renameColumn('unrealized_gain_loss', 'unrealized_pnl');
            }
            if (Schema::hasColumn('currency_positions', 'last_revalued_at')) {
                $table->renameColumn('last_revalued_at', 'last_valuation_at');
            }
            $table->dropColumn(['total_cost', 'current_value']);
        });
    }
};
