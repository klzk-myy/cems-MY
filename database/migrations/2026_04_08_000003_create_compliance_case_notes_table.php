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
        Schema::create('compliance_case_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id');
            $table->unsignedBigInteger('author_id');
            $table->string('note_type'); // CaseNoteType enum
            $table->text('content');
            $table->boolean('is_internal')->default(true);
            $table->timestamps();

            $table->foreign('case_id')->references('id')->on('compliance_cases')->onDelete('cascade');
            $table->foreign('author_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compliance_case_notes');
    }
};
