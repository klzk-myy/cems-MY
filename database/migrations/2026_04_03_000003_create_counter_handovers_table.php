<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

    public function down(): void
    {
        Schema::dropIfExists('counter_handovers');
    }
};
