<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->id();
            $table->string('year_code', 10)->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['Open', 'Closed', 'Archived'])->default('Open');
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('accounting_periods', function (Blueprint $table) {
            $table->foreignId('fiscal_year_id')->nullable()->after('id')->constrained();
        });
    }

    public function down(): void
    {
        Schema::table('accounting_periods', function (Blueprint $table) {
            $table->dropForeign(['fiscal_year_id']);
            $table->dropColumn('fiscal_year_id');
        });
        Schema::dropIfExists('fiscal_years');
    }
};
