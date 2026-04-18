<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_logs', function (Blueprint $table) {
            $table->string('previous_hash', 64)->nullable()->after('session_id');
            $table->string('entry_hash', 64)->nullable()->after('previous_hash');
            $table->index('previous_hash');
        });
    }

    public function down(): void
    {
        Schema::table('system_logs', function (Blueprint $table) {
            $table->dropIndex(['previous_hash']);
            $table->dropColumn(['previous_hash', 'entry_hash']);
        });
    }
};
