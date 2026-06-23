<?php

/**
 * Consolidated Migration: Customer Documents, Risk & EDD Tables
 * Replaces: 2025_03_31_000011, 2025_03_31_000017, 2026_04_05_000001,
 *          2026_04_08_000020, 2026_04_05_000006
 *
 * Creates: customer_documents, customer_risk_history, enhanced_diligence_records
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Customer documents table (full schema with verification columns)
        if (! Schema::hasTable('customer_documents')) {
            Schema::create('customer_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained()->onDelete('cascade');
                $table->enum('document_type', ['MyKad', 'Passport', 'Proof_of_Address', 'Others']);
                $table->string('file_path', 500);
                $table->string('file_hash', 64);
                $table->integer('file_size')->nullable();
                $table->boolean('encrypted')->default(true);
                $table->foreignId('uploaded_by')->constrained('users');
                $table->unsignedBigInteger('verified_by')->nullable();
                $table->foreign('verified_by')->references('id')->on('users')->nullOnDelete();
                $table->dateTime('verified_at')->nullable();
                $table->date('expiry_date')->nullable();
                $table->string('status')->default('pending');
                $table->timestamps();
                $table->index('customer_id');
                $table->index('document_type');
            });
        }

        // Customer risk history table
        if (! Schema::hasTable('customer_risk_history')) {
            Schema::create('customer_risk_history', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained()->onDelete('cascade');
                $table->string('previous_rating')->nullable();
                $table->string('new_rating')->nullable();
                $table->integer('previous_score')->nullable();
                $table->integer('new_score')->nullable();
                $table->string('change_reason')->nullable();
                $table->foreignId('changed_by')->nullable()->constrained('users');
                $table->timestamp('changed_at')->useCurrent();
                $table->timestamps();
                $table->index(['customer_id', 'changed_at']);
            });
        }

        // Enhanced diligence records table
        if (! Schema::hasTable('enhanced_diligence_records')) {
            Schema::create('enhanced_diligence_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained()->onDelete('cascade');
                $table->foreignId('flagged_transaction_id')->nullable()->constrained()->onDelete('cascade');
                $table->string('edd_level');
                $table->string('status')->default('in_progress');
                $table->json('responses')->nullable();
                $table->json('documents_received')->nullable();
                $table->timestamp('started_at')->useCurrent();
                $table->timestamp('completed_at')->nullable();
                $table->foreignId('assigned_to')->nullable()->constrained('users');
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['customer_id', 'status']);
                $table->index('started_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('enhanced_diligence_records');
        Schema::dropIfExists('customer_risk_history');
        Schema::dropIfExists('customer_documents');
    }
};
