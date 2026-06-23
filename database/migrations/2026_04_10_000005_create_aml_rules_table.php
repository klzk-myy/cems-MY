<?php

/**
 * Consolidated Migration: AML Rules Table
 * Replaces: 2026_04_05_000002_create_aml_rules_table,
 *          2026_04_05_000003_alter_aml_rules_table
 *
 * Creates: aml_rules (with final schema after alterations)
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // AML rules table (final schema after create + alter)
        if (! Schema::hasTable('aml_rules')) {
            Schema::create('aml_rules', function (Blueprint $table) {
                $table->id();
                $table->string('rule_code', 50)->unique();
                $table->string('rule_name', 100);
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->json('conditions')->nullable();
                $table->string('rule_type', 50)->nullable();
                $table->string('action', 20)->default('flag');
                $table->integer('risk_score')->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->index('is_active');
                $table->index('rule_type');
                $table->index('action');
                $table->index('risk_score');
            });

            // Seed default AML rules
            DB::table('aml_rules')->insert([
                [
                    'rule_code' => 'LARGE_CASH',
                    'rule_name' => 'Large Cash Transaction',
                    'description' => 'Flag transactions with cash amounts >= RM 50,000',
                    'is_active' => true,
                    'conditions' => json_encode(['amount_min' => 50000, 'payment_method' => 'cash']),
                    'rule_type' => 'threshold',
                    'action' => 'flag',
                    'risk_score' => 30,
                    'created_by' => null,
                ],
                [
                    'rule_code' => 'STRUCTURING',
                    'rule_name' => 'Structuring Detection',
                    'description' => 'Flag multiple transactions from same customer within 7 days that aggregate >= RM 50,000',
                    'is_active' => true,
                    'conditions' => json_encode(['lookback_days' => 7, 'aggregate_min' => 50000]),
                    'rule_type' => 'aggregation',
                    'action' => 'flag',
                    'risk_score' => 50,
                    'created_by' => null,
                ],
                [
                    'rule_code' => 'HIGH_RISK_COUNTRY',
                    'rule_name' => 'High Risk Country',
                    'description' => 'Flag transactions involving high-risk countries',
                    'is_active' => true,
                    'conditions' => json_encode(['risk_levels' => ['High', 'Grey']]),
                    'rule_type' => 'geographic',
                    'action' => 'flag',
                    'risk_score' => 40,
                    'created_by' => null,
                ],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('aml_rules');
    }
};
