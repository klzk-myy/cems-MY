<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_breach_alerts', function (Blueprint $table) {
            $table->id();
            $table->enum('alert_type', ['Mass_Access', 'Unauthorized', 'Export_Anomaly']);
            $table->enum('severity', ['Low', 'Medium', 'High', 'Critical']);
            $table->text('description');
            $table->integer('record_count')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users');
            $table->string('ip_address', 45)->nullable();
            $table->boolean('is_resolved')->default(false);
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index('severity');
            $table->index('is_resolved');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_breach_alerts');
    }
};
