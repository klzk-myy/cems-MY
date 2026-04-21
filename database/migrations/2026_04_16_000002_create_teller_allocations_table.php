<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teller_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->foreignId('counter_id')->nullable()->constrained()->onDelete('set null');
            $table->string('currency_code', 3);
            $table->decimal('allocated_amount', 20, 4);
            $table->decimal('current_balance', 20, 4);
            $table->decimal('requested_amount', 20, 4);
            $table->decimal('daily_limit_myr', 20, 4)->default(0);
            $table->decimal('daily_used_myr', 20, 4)->default(0);
            $table->enum('status', ['pending', 'approved', 'active', 'returned', 'closed', 'auto_returned'])->default('pending');
            $table->date('session_date');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'currency_code', 'session_date', 'status'], 'teller_alloc_user_currency_date_status_idx');
            $table->index(['counter_id', 'session_date'], 'teller_alloc_counter_date_idx');
            $table->index(['branch_id', 'session_date'], 'teller_alloc_branch_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teller_allocations');
    }
};
