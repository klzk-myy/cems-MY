<?php

/**
 * Consolidated Migration: Counter/Till Tables
 * Replaces: 2025_03_31_000014, 2026_04_03_000001, 2026_04_03_000002,
 *          2026_04_03_000003, 2026_04_03_063040
 *
 * Creates: till_balances, counters, counter_sessions, counter_handovers
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Till balances table
        if (! Schema::hasTable('till_balances')) {
            Schema::create('till_balances', function (Blueprint $table) {
                $table->id();
                $table->string('till_id', 50);
                $table->string('currency_code', 3);
                $table->decimal('opening_balance', 18, 4);
                $table->decimal('closing_balance', 18, 4)->nullable();
                $table->decimal('variance', 18, 4)->nullable();
                $table->decimal('transaction_total', 18, 4)->default(0);
                $table->decimal('foreign_total', 18, 4)->default(0);
                $table->date('date');
                $table->foreignId('opened_by')->constrained('users');
                $table->foreignId('closed_by')->nullable()->constrained('users');
                $table->timestamp('closed_at')->nullable();
                $table->text('notes')->nullable();
                $table->unique(['till_id', 'date', 'currency_code']);
                $table->foreign('currency_code')->references('code')->on('currencies');
                $table->index('date');
            });
        }

        // Counters table
        if (! Schema::hasTable('counters')) {
            Schema::create('counters', function (Blueprint $table) {
                $table->id();
                $table->string('code', 10)->unique();
                $table->string('name', 50);
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->timestamps();
                $table->index('status');
            });
        }

        // Counter sessions table
        if (! Schema::hasTable('counter_sessions')) {
            Schema::create('counter_sessions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('counter_id')->constrained('counters')->restrictOnDelete();
                $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
                $table->date('session_date');
                $table->dateTime('opened_at');
                $table->dateTime('closed_at')->nullable();
                $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
                $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->enum('status', ['open', 'closed', 'handed_over'])->default('open');
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['counter_id', 'session_date']);
                $table->index('status');
                $table->index('user_id');
            });
        }

        // Counter handovers table
        if (! Schema::hasTable('counter_handovers')) {
            Schema::create('counter_handovers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('counter_session_id')->constrained('counter_sessions')->restrictOnDelete();
                $table->foreignId('from_user_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('to_user_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('supervisor_id')->constrained('users')->restrictOnDelete();
                $table->dateTime('handover_time');
                $table->boolean('physical_count_verified')->default(true);
                $table->decimal('variance_myr', 15, 2)->default(0.00);
                $table->text('variance_notes')->nullable();
                $table->timestamps();
                $table->index('counter_session_id');
                $table->index('from_user_id');
                $table->index('to_user_id');
                $table->index('supervisor_id');
                $table->index('handover_time');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('counter_handovers');
        Schema::dropIfExists('counter_sessions');
        Schema::dropIfExists('counters');
        Schema::dropIfExists('till_balances');
    }
};
