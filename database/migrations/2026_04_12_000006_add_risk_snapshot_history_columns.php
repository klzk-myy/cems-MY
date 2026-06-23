<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('risk_score_snapshots', function (Blueprint $table) {
            $table->unsignedTinyInteger('previous_score')->nullable()->after('customer_id');
            $table->string('previous_rating')->nullable()->after('previous_score');
            $table->string('overall_rating_label')->nullable()->after('overall_score');
        });
    }

    public function down(): void
    {
        Schema::table('risk_score_snapshots', function (Blueprint $table) {
            $table->dropColumn(['previous_score', 'previous_rating', 'overall_rating_label']);
        });
    }
};
