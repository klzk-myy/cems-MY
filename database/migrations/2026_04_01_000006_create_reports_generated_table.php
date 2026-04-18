<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports_generated', function (Blueprint $table) {
            $table->id();
            $table->string('report_type', 50)->nullable(false);
            $table->date('period_start')->nullable(false);
            $table->date('period_end')->nullable(false);
            $table->foreignId('generated_by')->nullable(false)->constrained('users');
            $table->timestamp('generated_at')->useCurrent();
            $table->string('file_path', 500)->nullable();
            $table->enum('file_format', ['CSV', 'PDF', 'XLSX'])->nullable(false);
            $table->timestamps();

            $table->index(['report_type', 'period_start', 'period_end']);
            $table->index('generated_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports_generated');
    }
};
