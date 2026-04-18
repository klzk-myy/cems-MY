<?php

/**
 * Consolidated Migration: MFA and Authentication Tables
 * Replaces: 2025_03_31_000003_create_mfa_recovery_codes_table,
 *          2025_03_31_000004_create_device_computations_table,
 *          2026_04_02_000003_create_transaction_imports_table
 *
 * Creates: mfa_recovery_codes, device_computations, transaction_imports
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MFA recovery codes table
        if (! Schema::hasTable('mfa_recovery_codes')) {
            Schema::create('mfa_recovery_codes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('code', 32);
                $table->boolean('used')->default(false);
                $table->timestamp('used_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['user_id', 'used']);
            });
        }

        // Device computations table (TOTP/FIDO2)
        if (! Schema::hasTable('device_computations')) {
            Schema::create('device_computations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('device_name', 100)->nullable();
                $table->string('device_type', 20);
                $table->string('public_key', 500)->nullable();
                $table->string('algorithm', 10)->default('SHA256');
                $table->integer('counter')->default(0);
                $table->text('credential_ip')->nullable();
                $table->string('status', 20)->default('active');
                $table->timestamp('registered_at')->useCurrent();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'status']);
                $table->index('device_type');
            });
        }

        // Transaction imports table
        if (! Schema::hasTable('transaction_imports')) {
            Schema::create('transaction_imports', function (Blueprint $table) {
                $table->id();
                $table->string('filename', 255);
                $table->string('original_filename', 255);
                $table->string('file_hash', 64);
                $table->unsignedBigInteger('file_size');
                $table->string('status', 20)->default('pending');
                $table->unsignedInteger('total_rows')->default(0);
                $table->unsignedInteger('processed_rows')->default(0);
                $table->unsignedInteger('success_count')->default(0);
                $table->unsignedInteger('error_count')->default(0);
                $table->json('error_details')->nullable();
                $table->foreignId('imported_by')->nullable()->constrained('users');
                $table->timestamp('imported_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->index(['status', 'imported_at']);
                $table->index('imported_by');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_imports');
        Schema::dropIfExists('device_computations');
        Schema::dropIfExists('mfa_recovery_codes');
    }
};
