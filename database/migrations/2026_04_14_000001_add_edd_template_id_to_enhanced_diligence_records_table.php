<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add edd_template_id to enhanced_diligence_records.
     *
     * NOTE: This migration was retrofitted to reference `edd_questionnaire_templates`
     * instead of `edd_templates` so that fresh environments match the
     * EnhancedDiligenceRecord::template() relationship, which points to
     * App\Models\Compliance\EddQuestionnaireTemplate. The original migration
     * incorrectly referenced `edd_templates`. Environments that already ran the
     * original migration are repaired by
     * 2026_06_21_000011_fix_enhanced_diligence_template_foreign_key.php.
     */
    public function up(): void
    {
        Schema::table('enhanced_diligence_records', function (Blueprint $table) {
            $table->foreignId('edd_template_id')->nullable()->after('questionnaire_completed_by')
                ->constrained('edd_questionnaire_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('enhanced_diligence_records', function (Blueprint $table) {
            $table->dropForeign(['edd_template_id']);
            $table->dropColumn('edd_template_id');
        });
    }
};
