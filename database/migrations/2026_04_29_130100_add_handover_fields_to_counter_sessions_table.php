<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('counter_sessions', function (Blueprint $table) {
            $table->boolean('physical_count_verified')->nullable()->after('notes');
            $table->string('handover_notes', 500)->nullable()->after('physical_count_verified');
        });
    }

    public function down(): void
    {
        Schema::table('counter_sessions', function (Blueprint $table) {
            $table->dropColumn(['physical_count_verified', 'handover_notes']);
        });
    }
};
