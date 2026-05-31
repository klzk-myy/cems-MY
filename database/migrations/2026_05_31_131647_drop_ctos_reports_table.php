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
        Schema::dropIfExists('ctos_reports');
    }

    public function down(): void
    {
        Schema::create('ctos_reports', function (Blueprint $table) {
            $table->id();
            $table->string('ctos_number', 20)->unique();
            $table->timestamps();
        });
    }
};
