<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * pd-00.md 14C.13.1(d): Senior Management approval before establishing
     * or continuing business relationship with PEPs. For PEPs, Senior Management
     * refers to Senior Management at the head office.
     */
    public function up(): void
    {
        Schema::create('pep_approval_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->string('transaction_type', 50); // e.g., 'new_relationship', 'continued_relationship'
            $table->enum('status', ['pending', 'approved', 'rejected', 'expired'])->default('pending');
            $table->string('approval_level', 50)->default('head_office_senior_management');
            $table->timestamp('requested_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->foreign('customer_id')
                ->references('id')
                ->on('customers')
                ->onDelete('cascade');

            $table->foreign('approved_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->foreign('rejected_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index('customer_id');
            $table->index('status');
            $table->index('approval_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pep_approval_requests');
    }
};
