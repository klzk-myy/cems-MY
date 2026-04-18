<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->nullable(false);
            $table->enum('report_type', ['LCTR', 'MSB2', 'Trial_Balance', 'PL', 'Balance_Sheet', 'Currency_Position'])->nullable(false);
            $table->json('template_config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('report_type');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_templates');
    }
};
