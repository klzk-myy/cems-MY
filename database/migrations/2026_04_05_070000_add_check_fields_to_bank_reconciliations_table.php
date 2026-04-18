<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_reconciliations', function (Blueprint $table) {
            // Check-specific fields for outstanding checks tracking
            $table->string('check_number', 50)->nullable()->after('reference');
            $table->date('check_date')->nullable()->after('check_number');
            $table->enum('check_status', ['issued', 'presented', 'cleared', 'returned', 'stopped'])->nullable()->after('check_date');

            // For linking to journal entries that represent check issuances
            $table->string('check_payee', 255)->nullable()->after('check_status');

            $table->index('check_number');
            $table->index('check_status');
        });
    }

    public function down(): void
    {
        Schema::table('bank_reconciliations', function (Blueprint $table) {
            $table->dropIndex(['check_number']);
            $table->dropIndex(['check_status']);
            $table->dropColumn([
                'check_number',
                'check_date',
                'check_status',
                'check_payee',
            ]);
        });
    }
};
