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
        Schema::create('compliance_findings', function (Blueprint $table) {
            $table->id();
            $table->string('finding_type');       // FindingType enum value
            $table->string('severity');            // FindingSeverity enum value
            $table->string('subject_type');        // 'Customer' or 'Transaction'
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compliance_findings');
    }
};
