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
        Schema::create('compliance_cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_number'); // CASE-2026-00001
            $table->string('case_type');   // ComplianceCaseType enum
            $table->string('status')->default('Open');
            $table->string('severity');
            $table->string('priority');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('primary_flag_id')->nullable();
            $table->unsignedBigInteger('primary_finding_id')->nullable();
            $table->unsignedBigInteger('assigned_to');
            $table->text('case_summary')->nullable();
            $table->timestamp('sla_deadline');
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->json('metadata')->nullable();
            $table->string('created_via'); // 'Automated' or 'Manual'
            $table->timestamps();

            $table->index('case_number', 'compliance_cases_case_number_idx');
            $table->index(['case_type', 'status']);
            $table->index(['severity', 'status']);
            $table->index(['customer_id']);
            $table->index(['assigned_to']);
            $table->index('sla_deadline');

            // Foreign key constraints (without onDelete for now to avoid circular dependency issues)
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('primary_flag_id')->references('id')->on('flagged_transactions');
            $table->foreign('primary_finding_id')->references('id')->on('compliance_findings');
            $table->foreign('assigned_to')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compliance_cases');
    }
};
