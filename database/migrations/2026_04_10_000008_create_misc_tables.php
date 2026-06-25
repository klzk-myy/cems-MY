<?php

/**
 * Consolidated Migration: Miscellaneous Tables
 * Replaces: 2025_03_31_000011_create_customer_risk_history_table (moved),
 *          2025_03_31_000016_create_data_breach_alerts_table,
 *          2026_04_01_000005_create_report_templates_table,
 *          2026_04_01_000006_create_reports_generated_table,
 *          2026_04_05_000011_create_tasks_table,
 *          2026_04_05_000012_create_stock_transfers_tables,
 *          2026_04_05_060000_create_transaction_confirmations_table
 *
 * Creates: data_breach_alerts, report_templates, reports_generated,
 *          tasks, stock_transfers, stock_transfer_items, transaction_confirmations
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Data breach alerts table
        if (! Schema::hasTable('data_breach_alerts')) {
            Schema::create('data_breach_alerts', function (Blueprint $table) {
                $table->id();
                $table->string('alert_type', 50);
                $table->text('description');
                $table->string('severity', 20);
                $table->string('status', 20)->default('open');
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->json('affected_data')->nullable();
                $table->json('source_info')->nullable();
                $table->foreignId('detected_by')->nullable()->constrained('users');
                $table->timestamp('detected_at')->useCurrent();
                $table->foreignId('resolved_by')->nullable()->constrained('users');
                $table->timestamp('resolved_at')->nullable();
                $table->text('resolution_notes')->nullable();
                $table->timestamps();
                $table->index(['status', 'severity']);
                $table->index('customer_id');
            });
        }

        // Report templates table
        if (! Schema::hasTable('report_templates')) {
            Schema::create('report_templates', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->string('type', 50);
                $table->text('description')->nullable();
                $table->json('parameters')->nullable();
                $table->json('columns')->nullable();
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users');
                $table->timestamps();
                $table->index(['type', 'is_active']);
            });
        }

        // Reports generated table
        if (! Schema::hasTable('reports_generated')) {
            Schema::create('reports_generated', function (Blueprint $table) {
                $table->id();
                $table->string('report_type', 50);
                $table->string('report_name', 100);
                $table->string('period_start', 20)->nullable();
                $table->string('period_end', 20)->nullable();
                $table->string('file_path')->nullable();
                $table->string('format', 10)->default('xlsx');
                $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
                $table->foreignId('generated_by')->nullable()->constrained('users');
                $table->timestamp('generated_at')->nullable();
                $table->unsignedInteger('row_count')->default(0);
                $table->text('error_message')->nullable();
                $table->timestamps();
                $table->index(['report_type', 'status']);
                $table->index('generated_at');
            });
        }

        // Tasks table
        if (! Schema::hasTable('tasks')) {
            Schema::create('tasks', function (Blueprint $table) {
                $table->id();
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->string('type', 50);
                $table->string('priority', 20)->default('medium');
                $table->string('status', 20)->default('pending');
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->string('reference_type', 50)->nullable();
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('due_date')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['status', 'due_date']);
                $table->index(['assigned_to']);
                $table->index(['reference_type', 'reference_id']);
            });
        }

        // Stock transfers table
        if (! Schema::hasTable('stock_transfers')) {
            Schema::create('stock_transfers', function (Blueprint $table) {
                $table->id();
                $table->string('transfer_number', 50)->unique();
                $table->string('from_branch', 50);
                $table->string('to_branch', 50);
                $table->enum('status', ['pending', 'approved', 'in_transit', 'received', 'cancelled'])->default('pending');
                $table->foreignId('requested_by')->nullable()->constrained('users');
                $table->foreignId('approved_by')->nullable()->constrained('users');
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('received_by')->nullable()->constrained('users');
                $table->timestamp('received_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['from_branch', 'to_branch']);
                $table->index('status');
            });
        }

        // Stock transfer items table
        if (! Schema::hasTable('stock_transfer_items')) {
            Schema::create('stock_transfer_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('transfer_id')->constrained('stock_transfers')->onDelete('cascade');
                $table->string('currency_code', 3);
                $table->decimal('quantity', 18, 4);
                $table->decimal('rate', 18, 6);
                $table->decimal('value_myr', 18, 4);
                $table->timestamps();
                $table->index('transfer_id');
                $table->foreign('currency_code')->references('code')->on('currencies');
            });
        }

        // Transaction confirmations table
        if (! Schema::hasTable('transaction_confirmations')) {
            Schema::create('transaction_confirmations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('transaction_id')->constrained()->onDelete('cascade');
                $table->foreignId('confirmed_by')->nullable()->constrained('users');
                $table->timestamp('confirmed_at')->useCurrent();
                $table->string('confirmation_method', 20)->default('system');
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index('transaction_id');
                $table->index(['confirmed_by']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_confirmations');
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('reports_generated');
        Schema::dropIfExists('report_templates');
        Schema::dropIfExists('data_breach_alerts');
    }
};
