<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds pep_type column to support differentiated PEP handling per pd-00.md:
     * - Foreign PEPs (15.2) - always require Enhanced CDD
     * - Domestic PEPs (15.3) - risk-based Enhanced CDD approach
     * - International Organisation, Family Member, Close Associate - risk-based
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('pep_type', 50)->nullable()->after('pep_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('pep_type');
        });
    }
};
