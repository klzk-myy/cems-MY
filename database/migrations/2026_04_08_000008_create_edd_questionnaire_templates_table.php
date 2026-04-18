<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edd_questionnaire_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->json('questions')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edd_questionnaire_templates');
    }
};
