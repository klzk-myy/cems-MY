<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mirrors sanction_import_logs structure. Deliberately NOT reusing
     * sanction_import_logs because its list_id is a NOT NULL foreign key
     * tied to the sanction_lists lifecycle (cascade delete), which cannot
     * truthfully represent file-driven adverse media imports.
     */
    public function up(): void
    {
        Schema::create('adverse_media_import_logs', function (Blueprint $table) {
            $table->id();
            $table->string('imported_file')->nullable();
            $table->timestamp('imported_at');
            $table->integer('records_added')->default(0);
            $table->integer('records_updated')->default(0);
            $table->integer('records_deactivated')->default(0);
            $table->integer('records_skipped')->default(0);
            $table->enum('status', ['success', 'partial', 'failed'])->default('success');
            $table->text('error_message')->nullable();
            $table->enum('triggered_by', ['scheduled', 'manual'])->default('manual');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['imported_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adverse_media_import_logs');
    }
};
