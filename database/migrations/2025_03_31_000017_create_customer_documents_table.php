<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->enum('document_type', ['MyKad', 'Passport', 'Proof_of_Address', 'Others']);
            $table->string('file_path', 500);
            $table->string('file_hash', 64);
            $table->integer('file_size')->nullable();
            $table->boolean('encrypted')->default(true);
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
            $table->index('customer_id');
            $table->index('document_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_documents');
    }
};
