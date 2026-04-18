<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enhanced_diligence_records', function (Blueprint $table) {
            $table->foreignId('edd_template_id')->nullable()->after('questionnaire_completed_by')
                ->constrained('edd_templates')->nullOnDelete();
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
