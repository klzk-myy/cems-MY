<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enhanced_diligence_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flagged_transaction_id')->nullable()->constrained('flagged_transactions');
            $table->foreignId('customer_id')->constrained('customers');
            $table->string('edd_reference', 30)->unique(); // EDD-YYYYMM-XXXX
            $table->enum('status', ['Incomplete', 'Pending_Questionnaire', 'Questionnaire_Submitted', 'Pending_Review', 'Approved', 'Rejected', 'Expired'])->default('Incomplete');
            $table->enum('risk_level', ['Low', 'Medium', 'High', 'Critical'])->default('Medium');

            // Source of Funds
            $table->text('source_of_funds')->nullable();
            $table->text('source_of_funds_description')->nullable();
            $table->json('source_of_funds_documents')->nullable();

            // Purpose of Transaction
            $table->text('purpose_of_transaction')->nullable();
            $table->text('business_justification')->nullable();

            // Customer Information
            $table->text('employment_status')->nullable();
            $table->string('employer_name', 200)->nullable();
            $table->string('employer_address', 500)->nullable();
            $table->text('annual_income_range')->nullable();
            $table->text('estimated_net_worth')->nullable();

            // Source of Wealth
            $table->text('source_of_wealth')->nullable();
            $table->text('source_of_wealth_description')->nullable();

            // Additional Information
            $table->text('additional_information')->nullable();
            $table->json('supporting_documents')->nullable();

            // Review
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();

            // Questionnaire
            $table->json('questionnaire_responses')->nullable();
            $table->timestamp('questionnaire_completed_at')->nullable();
            $table->foreignId('questionnaire_completed_by')->nullable()->constrained('users');

            // Approval
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();

            $table->index('edd_reference');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enhanced_diligence_records');
    }
};
