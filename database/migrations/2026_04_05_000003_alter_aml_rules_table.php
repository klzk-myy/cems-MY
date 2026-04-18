<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            // SQLite test runs use the consolidated AML rules migration instead.
            return;
        }

        // MySQL: use renameColumn for efficiency
        Schema::table('aml_rules', function (Blueprint $table) {
            $table->renameColumn('is_enabled', 'is_active');
        });

        Schema::table('aml_rules', function (Blueprint $table) {
            $table->renameColumn('parameters', 'conditions');
        });

        Schema::table('aml_rules', function (Blueprint $table) {
            $table->string('rule_type', 50)->nullable();
            $table->string('action', 20)->default('flag');
            $table->integer('risk_score')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->dropColumn(['priority', 'flag_type']);
            $table->index('rule_type');
            $table->index('action');
            $table->index('risk_score');
        });
    }

    public function down(): void
    {
        Schema::table('aml_rules', function (Blueprint $table) {
            $table->dropIndex(['rule_type']);
            $table->dropIndex(['action']);
            $table->dropIndex(['risk_score']);
            $table->dropColumn(['rule_type', 'action', 'risk_score', 'created_by']);
            $table->integer('priority')->default(100);
            $table->string('flag_type', 50);
        });

        Schema::table('aml_rules', function (Blueprint $table) {
            $table->renameColumn('conditions', 'parameters');
        });

        Schema::table('aml_rules', function (Blueprint $table) {
            $table->renameColumn('is_active', 'is_enabled');
        });
    }
};
