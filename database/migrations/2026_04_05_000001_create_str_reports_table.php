<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('str_reports', function (Blueprint $table) {
            $table->id();
            $table->string('str_no', 50)->unique();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('alert_id')->nullable();
            $table->json('transaction_ids');
            $table->text('reason');
            $table->json('supporting_documents')->nullable();
            $table->string('status', 30)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->string('bnm_reference', 100)->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('suspicion_date')->nullable()->comment('When suspicion first arose');
            $table->timestamp('filing_deadline')->nullable()->comment('BNM 3 working day deadline');
            $table->timestamps();

            $table->index('status');
            $table->index('branch_id');
            $table->index('customer_id');
            $table->index('created_by');
            $table->index('submitted_at');
            $table->index('filing_deadline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('str_reports');
    }
};
