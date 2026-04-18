<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanction_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('list_id')->constrained('sanction_lists');
            $table->string('entity_name', 255);
            $table->enum('entity_type', ['Individual', 'Entity'])->default('Individual');
            $table->text('aliases')->nullable();
            $table->string('nationality', 100)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->json('details')->nullable();
            $table->index('list_id');
            $table->index('entity_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanction_entries');
    }
};
