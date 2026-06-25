<?php

/**
 * Consolidated Migration: Compliance Tables
 * Replaces: 2026_04_08_000001 through 2026_04_08_000009,
 *          2026_04_07_000000 (alerts, str_drafts, etc - partial),
 *          plus 2026_04_08_000002_create_compliance_cases_table
 *
 * Creates: compliance_findings, compliance_cases, compliance_case_notes,
 *          compliance_case_documents, compliance_case_links,
 *          customer_risk_profiles, customer_behavioral_baselines,
 *          edd_questionnaire_templates, edd_document_requests,
 *          alerts, str_drafts, risk_score_snapshots, edd_templates,
 *          report_schedules, report_runs
 *
 * NOTE: compliance_cases MUST be created before alerts due to FK dependency
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Compliance findings table
        if (! Schema::hasTable('compliance_findings')) {
            Schema::create('compliance_findings', function (Blueprint $table) {
                $table->id();
                $table->string('finding_type');
                $table->string('severity');
                $table->string('subject_type');
                $table->unsignedBigInteger('subject_id');
                $table->json('details')->nullable();
                $table->string('status')->default('New');
                $table->timestamp('generated_at');
                $table->timestamps();
                $table->index(['finding_type', 'status']);
                $table->index(['subject_type', 'subject_id']);
                $table->index(['severity', 'status']);
                $table->index('generated_at');
            });
        }

        // Compliance cases table (created BEFORE alerts)
        if (! Schema::hasTable('compliance_cases')) {
            Schema::create('compliance_cases', function (Blueprint $table) {
                $table->id();
                $table->string('case_number');
                $table->string('case_type');
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
                $table->string('created_via');
                $table->timestamps();
                $table->index('case_number', 'compliance_cases_case_number_idx');
                $table->index(['case_type', 'status']);
                $table->index(['severity', 'status']);
                $table->index(['customer_id']);
                $table->index(['assigned_to']);
                $table->index('sla_deadline');
                $table->foreign('customer_id')->references('id')->on('customers');
                $table->foreign('primary_flag_id')->references('id')->on('flagged_transactions');
                $table->foreign('primary_finding_id')->references('id')->on('compliance_findings');
                $table->foreign('assigned_to')->references('id')->on('users');
            });
        }

        // Compliance case notes table
        if (! Schema::hasTable('compliance_case_notes')) {
            Schema::create('compliance_case_notes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('case_id')->constrained('compliance_cases')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('users');
                $table->text('note');
                $table->string('type')->default('general');
                $table->timestamps();
                $table->index(['case_id']);
            });
        }

        // Compliance case documents table
        if (! Schema::hasTable('compliance_case_documents')) {
            Schema::create('compliance_case_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('case_id')->constrained('compliance_cases')->onDelete('cascade');
                $table->string('document_type');
                $table->string('file_path', 500);
                $table->string('file_hash', 64)->nullable();
                $table->integer('file_size')->nullable();
                $table->foreignId('uploaded_by')->constrained('users');
                $table->timestamp('uploaded_at')->useCurrent();
                $table->timestamps();
                $table->index(['case_id']);
            });
        }

        // Compliance case links table
        if (! Schema::hasTable('compliance_case_links')) {
            Schema::create('compliance_case_links', function (Blueprint $table) {
                $table->id();
                $table->foreignId('case_id')->constrained('compliance_cases')->onDelete('cascade');
                $table->string('linked_entity_type');
                $table->unsignedBigInteger('linked_entity_id');
                $table->string('relationship');
                $table->timestamps();
                $table->index(['case_id', 'linked_entity_type', 'linked_entity_id']);
            });
        }

        // Customer risk profiles table
        if (! Schema::hasTable('customer_risk_profiles')) {
            Schema::create('customer_risk_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained()->onDelete('cascade');
                $table->unsignedTinyInteger('overall_score')->default(0);
                $table->unsignedTinyInteger('velocity_score')->default(0);
                $table->unsignedTinyInteger('structuring_score')->default(0);
                $table->unsignedTinyInteger('geographic_score')->default(0);
                $table->unsignedTinyInteger('amount_score')->default(0);
                $table->unsignedTinyInteger('frequency_score')->default(0);
                $table->string('risk_rating')->default('Low');
                $table->string('trend')->default('stable');
                $table->json('risk_factors')->nullable();
                $table->timestamp('last_evaluated_at')->nullable();
                $table->timestamp('locked_at')->nullable();
                $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['customer_id']);
                $table->index(['risk_rating']);
                $table->index('last_evaluated_at');
            });
        }

        // Customer behavioral baselines table
        if (! Schema::hasTable('customer_behavioral_baselines')) {
            Schema::create('customer_behavioral_baselines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained()->onDelete('cascade');
                $table->string('metric_type');
                $table->decimal('typical_amount', 18, 4)->default(0);
                $table->decimal('average_amount', 18, 4)->default(0);
                $table->decimal('max_amount', 18, 4)->default(0);
                $table->integer('transaction_count')->default(0);
                $table->decimal('typical_frequency', 8, 2)->default(0);
                $table->string('common_currency', 3)->nullable();
                $table->json('common_purposes')->nullable();
                $table->decimal('standard_deviation', 18, 4)->nullable();
                $table->timestamp('computed_at')->nullable();
                $table->timestamps();
                $table->index(['customer_id', 'metric_type']);
            });
        }

        // EDD questionnaire templates table
        if (! Schema::hasTable('edd_questionnaire_templates')) {
            Schema::create('edd_questionnaire_templates', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('risk_level');
                $table->text('description')->nullable();
                $table->json('questions');
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('version')->default(1);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['risk_level', 'is_active']);
            });
        }

        // EDD document requests table
        if (! Schema::hasTable('edd_document_requests')) {
            Schema::create('edd_document_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained()->onDelete('cascade');
                $table->foreignId('case_id')->nullable()->constrained('compliance_cases')->nullOnDelete();
                $table->string('document_type');
                $table->text('description')->nullable();
                $table->string('status')->default('pending');
                $table->date('deadline')->nullable();
                $table->date('received_at')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
                $table->index(['customer_id', 'status']);
                $table->index('deadline');
            });
        }

        // Alerts table (references compliance_cases which was created above)
        if (! Schema::hasTable('alerts')) {
            Schema::create('alerts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('flagged_transaction_id')->nullable()->constrained('flagged_transactions')->nullOnDelete();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->string('type');
                $table->string('priority');
                $table->unsignedTinyInteger('risk_score')->default(0);
                $table->text('reason')->nullable();
                $table->string('source')->default('System');
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('case_id')->nullable()->constrained('compliance_cases')->nullOnDelete();
                $table->string('status', 30)->default('Open');
                $table->timestamps();
                $table->index(['priority', 'case_id']);
                $table->index(['customer_id', 'created_at']);
                $table->index(['assigned_to']);
                $table->index('status');
            });
        }

        // STR drafts table
        if (! Schema::hasTable('str_drafts')) {
            Schema::create('str_drafts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('case_id')->nullable()->constrained('compliance_cases')->nullOnDelete();
                $table->json('alert_ids')->nullable();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->json('transaction_ids')->nullable();
                $table->text('narrative')->nullable();
                $table->string('suspected_activity')->nullable();
                $table->unsignedTinyInteger('confidence_score')->default(0);
                $table->date('filing_deadline')->nullable();
                $table->string('status')->default('draft');
                $table->foreignId('converted_to_str_id')->nullable()->constrained('str_reports')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['case_id', 'status']);
                $table->index(['customer_id']);
                $table->index(['filing_deadline']);
            });
        }

        // Risk score snapshots table
        if (! Schema::hasTable('risk_score_snapshots')) {
            Schema::create('risk_score_snapshots', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->date('snapshot_date');
                $table->unsignedTinyInteger('overall_score')->default(0);
                $table->unsignedTinyInteger('velocity_score')->default(0);
                $table->unsignedTinyInteger('structuring_score')->default(0);
                $table->unsignedTinyInteger('geographic_score')->default(0);
                $table->unsignedTinyInteger('amount_score')->default(0);
                $table->string('trend')->default('stable');
                $table->json('factors')->nullable();
                $table->date('next_screening_date')->nullable();
                $table->timestamps();
                $table->index(['customer_id', 'snapshot_date']);
                $table->index(['next_screening_date']);
            });
        }

        // EDD templates table (additional)
        if (! Schema::hasTable('edd_templates')) {
            Schema::create('edd_templates', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('type');
                $table->text('description')->nullable();
                $table->json('questions')->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['type', 'is_active']);
            });
        }

        // Report schedules table
        if (! Schema::hasTable('report_schedules')) {
            Schema::create('report_schedules', function (Blueprint $table) {
                $table->id();
                $table->string('report_type');
                $table->string('cron_expression');
                $table->json('parameters')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_run_at')->nullable();
                $table->timestamp('next_run_at')->nullable();
                $table->json('notification_recipients')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['is_active', 'next_run_at']);
            });
        }

        // Report runs table
        if (! Schema::hasTable('report_runs')) {
            Schema::create('report_runs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('schedule_id')->nullable()->constrained('report_schedules')->nullOnDelete();
                $table->string('report_type');
                $table->json('parameters')->nullable();
                $table->string('status')->default('scheduled');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->string('file_path')->nullable();
                $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedInteger('row_count')->default(0);
                $table->text('error_message')->nullable();
                $table->unsignedInteger('downloaded_count')->default(0);
                $table->timestamps();
                $table->index(['report_type', 'status']);
                $table->index(['created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('report_runs');
        Schema::dropIfExists('report_schedules');
        Schema::dropIfExists('edd_templates');
        Schema::dropIfExists('risk_score_snapshots');
        Schema::dropIfExists('str_drafts');
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('edd_document_requests');
        Schema::dropIfExists('edd_questionnaire_templates');
        Schema::dropIfExists('customer_behavioral_baselines');
        Schema::dropIfExists('customer_risk_profiles');
        Schema::dropIfExists('compliance_case_links');
        Schema::dropIfExists('compliance_case_documents');
        Schema::dropIfExists('compliance_case_notes');
        Schema::dropIfExists('compliance_cases');
        Schema::dropIfExists('compliance_findings');
    }
};
