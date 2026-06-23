<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('system_health_checks', function (Blueprint $table) {
            $table->id();
            $table->string('check_name', 100)->index();
            $table->enum('status', ['ok', 'warning', 'critical'])->default('ok')->index();
            $table->text('message')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();

            // Composite index for common queries
            $table->index(['check_name', 'checked_at']);
            $table->index(['status', 'checked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_health_checks');
    }
};
