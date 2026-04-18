<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edd_document_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('edd_record_id');
            $table->string('document_type');
            $table->string('status')->default('Pending');
            $table->string('file_path')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamps();

            $table->foreign('edd_record_id')
                ->references('id')
                ->on('enhanced_diligence_records')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edd_document_requests');
    }
};
