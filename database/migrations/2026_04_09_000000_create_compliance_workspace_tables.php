<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
            $table->timestamps();

            $table->index(['priority', 'case_id']);
            $table->index(['customer_id', 'created_at']);
            $table->index(['assigned_to']);
        });

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

    public function down(): void
    {
        Schema::dropIfExists('report_runs');
        Schema::dropIfExists('report_schedules');
        Schema::dropIfExists('edd_templates');
        Schema::dropIfExists('risk_score_snapshots');
        Schema::dropIfExists('str_drafts');
        Schema::dropIfExists('alerts');
    }
};
