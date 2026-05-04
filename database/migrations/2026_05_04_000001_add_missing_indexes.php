<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_relations', function (Blueprint $table) {
            $table->index(['customer_id', 'related_customer_id', 'relation_type'], 'customer_relations_composite_idx');
        });

        Schema::table('alerts', function (Blueprint $table) {
            $table->index(['assigned_to', 'case_id', 'status'], 'alerts_composite_idx');
        });

        Schema::table('flagged_transactions', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'flagged_transactions_status_date_idx');
            $table->index(['flag_type', 'created_at'], 'flagged_transactions_flag_type_date_idx');
        });

        Schema::table('customer_risk_profiles', function (Blueprint $table) {
            $table->index('risk_tier', 'customer_risk_profiles_risk_tier_idx');
        });
    }

    public function down(): void
    {
        Schema::table('customer_relations', function (Blueprint $table) {
            $table->dropIndex('customer_relations_composite_idx');
        });

        Schema::table('alerts', function (Blueprint $table) {
            $table->dropIndex('alerts_composite_idx');
        });

        Schema::table('flagged_transactions', function (Blueprint $table) {
            $table->dropIndex('flagged_transactions_status_date_idx');
            $table->dropIndex('flagged_transactions_flag_type_date_idx');
        });

        Schema::table('customer_risk_profiles', function (Blueprint $table) {
            $table->dropIndex('customer_risk_profiles_risk_tier_idx');
        });
    }
};
