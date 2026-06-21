<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $foreignKeys = Schema::getForeignKeys('enhanced_diligence_records');
        $referencesEddTemplates = false;

        foreach ($foreignKeys as $key) {
            if (in_array('edd_template_id', $key['columns'] ?? [])
                && ($key['foreign_table'] ?? null) === 'edd_templates') {
                $referencesEddTemplates = true;
                break;
            }
        }

        if (! $referencesEddTemplates) {
            return;
        }

        Schema::table('enhanced_diligence_records', function (Blueprint $table) {
            $table->dropForeign(['edd_template_id']);
            $table->foreign('edd_template_id')
                ->references('id')
                ->on('edd_questionnaire_templates')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Intentionally empty; this is a forward-fix migration.
    }
};
