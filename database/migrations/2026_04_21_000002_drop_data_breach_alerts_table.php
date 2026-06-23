<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('data_breach_alerts');
    }

    public function down(): void
    {
        Schema::create('data_breach_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('triggered_by')->constrained('users');
            $table->string('alert_type');
            $table->text('description');
            $table->json('affected_records');
            $table->string('severity');
            $table->string('status')->default('open');
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['alert_type', 'severity']);
            $table->index('created_at');
        });
    }
};
