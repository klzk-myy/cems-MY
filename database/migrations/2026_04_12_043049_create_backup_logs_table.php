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
        Schema::create('backup_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('backup_name');
            $table->string('backup_type'); // database, files, full, archive, manual
            $table->string('disk'); // local, s3
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('checksum', 64)->nullable(); // SHA-256 hash
            $table->boolean('encryption_status')->default(false);
            $table->string('status')->default('pending'); // pending, running, completed, failed, verified, verification_failed
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->boolean('verification_status')->nullable();
            $table->text('verification_error')->nullable();
            $table->timestamps();

            // Indexes for querying
            $table->index('status');
            $table->index('backup_type');
            $table->index('disk');
            $table->index('started_at');
            $table->index('completed_at');
            $table->index(['status', 'started_at']);
            $table->index(['backup_type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backup_logs');
    }
};
