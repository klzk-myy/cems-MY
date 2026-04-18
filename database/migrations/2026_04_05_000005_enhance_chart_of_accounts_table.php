<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->string('account_class', 50)->nullable()->after('account_type');
            $table->boolean('allow_journal')->default(true)->after('is_active');
            $table->foreignId('cost_center_id')->nullable()->after('allow_journal');
            $table->foreignId('department_id')->nullable()->after('cost_center_id');
        });
    }

    public function down(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->dropForeign(['cost_center_id']);
            $table->dropForeign(['department_id']);
            $table->dropColumn(['account_class', 'allow_journal', 'cost_center_id', 'department_id']);
        });
    }
};
