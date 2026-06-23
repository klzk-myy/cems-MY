<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('str_drafts');
    }

    public function down(): void
    {
        Schema::create('str_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->nullable()->constrained('compliance_cases');
            $table->foreignId('customer_id')->constrained();
            $table->json('alert_ids')->nullable();
            $table->text('narrative')->nullable();
            $table->string('suspected_activity')->nullable();
            $table->unsignedTinyInteger('confidence_score')->nullable();
            $table->json('ai_metadata')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('filing_deadline')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['case_id', 'status']);
            $table->index(['customer_id', 'status']);
            $table->index('filing_deadline');
        });
    }
};
