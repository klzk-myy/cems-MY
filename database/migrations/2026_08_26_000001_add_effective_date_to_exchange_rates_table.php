<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exchange_rates', function (Blueprint $table) {
            // Overrides may be scheduled ahead of time: the row only feeds
            // rate lookups once effective_date has passed (NULL means active now).
            $table->timestamp('effective_date')->nullable()->after('fetched_at');
            $table->index(['currency_code', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::table('exchange_rates', function (Blueprint $table) {
            $table->dropIndex(['currency_code', 'effective_date']);
            $table->dropColumn('effective_date');
        });
    }
};
