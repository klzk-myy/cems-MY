<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();
            $table->string('period_code', 10)->unique(); // e.g., "2026-04"
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('period_type', ['month', 'quarter', 'year'])->default('month');
            $table->enum('status', ['open', 'closing', 'closed'])->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->index('start_date');
            $table->index('end_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_periods');
    }
};
