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
        Schema::create('compliance_case_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id');
            $table->string('linked_type'); // 'Transaction', 'EddRecord', 'StrReport', 'Customer'
            $table->unsignedBigInteger('linked_id');
            $table->timestamps();

            $table->foreign('case_id')->references('id')->on('compliance_cases')->onDelete('cascade');
            $table->index(['linked_type', 'linked_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compliance_case_links');
    }
};
