<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('counter_sessions', function (Blueprint $table) {
            $table->foreignId('teller_allocation_id')->nullable()->after('closed_by')->constrained('teller_allocations')->onDelete('set null');
            $table->decimal('requested_amount_myr', 20, 4)->nullable()->after('teller_allocation_id');
            $table->decimal('daily_limit_myr', 20, 4)->nullable()->after('requested_amount_myr');
        });
    }

    public function down(): void
    {
        Schema::table('counter_sessions', function (Blueprint $table) {
            $table->dropForeign(['teller_allocation_id']);
            $table->dropColumn('teller_allocation_id', 'requested_amount_myr', 'daily_limit_myr');
        });
    }
};
