<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add missing fields to customers table for comprehensive KYC tracking.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Add missing fields if they don't exist
            if (! Schema::hasColumn('customers', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('risk_rating');
            }

            if (! Schema::hasColumn('customers', 'sanction_hit')) {
                $table->boolean('sanction_hit')->default(false)->after('pep_status');
            }

            if (! Schema::hasColumn('customers', 'cdd_level')) {
                $table->enum('cdd_level', ['Simplified', 'Standard', 'Enhanced'])->default('Simplified')->after('risk_rating');
            }

            if (! Schema::hasColumn('customers', 'occupation')) {
                $table->string('occupation', 255)->nullable()->after('email');
            }

            if (! Schema::hasColumn('customers', 'employer_name')) {
                $table->string('employer_name', 255)->nullable()->after('occupation');
            }

            if (! Schema::hasColumn('customers', 'employer_address')) {
                $table->text('employer_address')->nullable()->after('employer_name');
            }

            if (! Schema::hasColumn('customers', 'annual_volume_estimate')) {
                $table->decimal('annual_volume_estimate', 20, 4)->nullable()->after('risk_rating');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop columns one at a time
        $columns = [
            'is_active',
            'sanction_hit',
            'cdd_level',
            'occupation',
            'employer_name',
            'employer_address',
            'annual_volume_estimate',
        ];

        foreach ($columns as $column) {
            if (Schema::hasColumn('customers', $column)) {
                Schema::table('customers', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
