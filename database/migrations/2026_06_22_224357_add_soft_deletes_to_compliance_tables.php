<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('compliance_cases', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('compliance_findings', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('alerts', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('flagged_transactions', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('sanction_entries', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('sanction_lists', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('screening_results', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('risk_score_snapshots', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('compliance_case_documents', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('compliance_case_notes', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('compliance_case_links', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('compliance_cases', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('compliance_findings', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('alerts', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('flagged_transactions', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('sanction_entries', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('sanction_lists', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('screening_results', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('risk_score_snapshots', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('compliance_case_documents', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('compliance_case_notes', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('compliance_case_links', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
