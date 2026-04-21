<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('str_reports', function (Blueprint $table) {
            // Fields from StrDraft to merge
            $table->foreignId('case_id')->nullable()->after('alert_id')->constrained('compliance_cases');
            $table->json('alert_ids')->nullable()->after('case_id');
            $table->text('narrative')->nullable()->after('reason');
            $table->text('suspected_activity')->nullable()->after('narrative');
            $table->integer('confidence_score')->nullable()->after('suspected_activity');
            $table->foreignId('converted_from_draft_id')->nullable()->after('confidence_score');
        });
    }

    public function down(): void
    {
        Schema::table('str_reports', function (Blueprint $table) {
            $table->dropForeign(['case_id']);
            $table->dropColumn([
                'case_id',
                'alert_ids',
                'narrative',
                'suspected_activity',
                'confidence_score',
                'converted_from_draft_id',
            ]);
        });
    }
};
