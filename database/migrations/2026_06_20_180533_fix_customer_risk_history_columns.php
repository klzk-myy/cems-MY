<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_risk_history', function (Blueprint $table) {
            $table->renameColumn('old_score', 'previous_score');
            $table->renameColumn('old_rating', 'previous_rating');
            $table->renameColumn('assessed_by', 'changed_by');
            $table->timestamp('changed_at')->nullable()->after('changed_by');
        });
    }

    public function down(): void
    {
        Schema::table('customer_risk_history', function (Blueprint $table) {
            $table->dropColumn('changed_at');
            $table->renameColumn('changed_by', 'assessed_by');
            $table->renameColumn('previous_rating', 'old_rating');
            $table->renameColumn('previous_score', 'old_score');
        });
    }
};
