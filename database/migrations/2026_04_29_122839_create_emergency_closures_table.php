<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emergency_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('counter_id')->constrained('counters')->restrictOnDelete();
            $table->foreignId('session_id')->constrained('counter_sessions')->restrictOnDelete();
            $table->foreignId('teller_id')->constrained('users')->restrictOnDelete();
            $table->string('reason')->nullable();
            $table->dateTime('closed_at');
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('acknowledged_at')->nullable();
            $table->timestamps();

            $table->index('counter_id');
            $table->index('teller_id');
            $table->index(['counter_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emergency_closures');
    }
};
