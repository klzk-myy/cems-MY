<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('till_balances', 'counter_session_id')) {
            Schema::table('till_balances', function (Blueprint $table) {
                $table->foreignId('teller_allocation_id')->nullable()->after('counter_session_id')->constrained('teller_allocations')->onDelete('set null');
            });
        } else {
            Schema::table('till_balances', function (Blueprint $table) {
                $table->foreignId('teller_allocation_id')->nullable()->constrained('teller_allocations')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::table('till_balances', function (Blueprint $table) {
            $table->dropForeign(['teller_allocation_id']);
            $table->dropColumn('teller_allocation_id');
        });
    }
};
