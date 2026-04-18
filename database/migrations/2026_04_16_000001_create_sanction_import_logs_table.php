<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanction_import_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('list_id')->constrained('sanction_lists')->cascadeOnDelete();
            $table->timestamp('imported_at');
            $table->integer('records_added')->default(0);
            $table->integer('records_updated')->default(0);
            $table->integer('records_deactivated')->default(0);
            $table->enum('status', ['success', 'partial', 'failed'])->default('success');
            $table->text('error_message')->nullable();
            $table->enum('triggered_by', ['scheduled', 'manual'])->default('scheduled');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['list_id', 'imported_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanction_import_logs');
    }
};
