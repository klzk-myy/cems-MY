<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('high_risk_countries', function (Blueprint $table) {
            $table->string('country_code', 2)->primary();
            $table->string('country_name', 100);
            $table->enum('risk_level', ['High', 'Grey']);
            $table->string('source', 50);
            $table->date('list_date');
            $table->timestamps();
            $table->index('risk_level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('high_risk_countries');
    }
};
