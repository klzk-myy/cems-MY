<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Repair environments where the original 2026_04_14_000001 migration already
     * created a foreign key from enhanced_diligence_records.edd_template_id to
     * edd_templates. This migration drops that incorrect constraint and recreates
     * it against edd_questionnaire_templates to match the
     * EnhancedDiligenceRecord::template() relationship.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (! in_array($driver, ['mysql', 'pgsql'])) {
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

    /**
     * Reverse the foreign-key change on MySQL/PostgreSQL. If the current
     * constraint references edd_questionnaire_templates, drop it and recreate it
     * against edd_templates. Other drivers are left untouched.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (! in_array($driver, ['mysql', 'pgsql'])) {
            return;
        }

        $foreignKeys = Schema::getForeignKeys('enhanced_diligence_records');
        $referencesEddQuestionnaireTemplates = false;

        foreach ($foreignKeys as $key) {
            if (in_array('edd_template_id', $key['columns'] ?? [])
                && ($key['foreign_table'] ?? null) === 'edd_questionnaire_templates') {
                $referencesEddQuestionnaireTemplates = true;
                break;
            }
        }

        if (! $referencesEddQuestionnaireTemplates) {
            return;
        }

        Schema::table('enhanced_diligence_records', function (Blueprint $table) {
            $table->dropForeign(['edd_template_id']);
            $table->foreign('edd_template_id')
                ->references('id')
                ->on('edd_templates')
                ->nullOnDelete();
        });
    }
};
