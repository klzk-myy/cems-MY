<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teller_allocations', function (Blueprint $table) {
            $table->datetime('rejected_at')->nullable()->after('closed_at');
            $table->foreignId('rejected_by')->nullable()->after('rejected_at');
            $table->string('rejection_reason', 500)->nullable()->after('rejected_by');
        });
    }

    public function down(): void
    {
        Schema::table('teller_allocations', function (Blueprint $table) {
            $table->dropColumn(['rejected_at', 'rejected_by', 'rejection_reason']);
        });
    }
};
