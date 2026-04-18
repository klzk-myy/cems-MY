<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

    public function down(): void
    {
        Schema::dropIfExists('counter_sessions');
    }
};
