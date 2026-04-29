<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('counter_handovers', function (Blueprint $table) {
            $table->dateTime('acknowledged_at')->nullable()->after('variance_notes');
        });
    }

    public function down(): void
    {
        Schema::table('counter_handovers', function (Blueprint $table) {
            $table->dropColumn('acknowledged_at');
        });
    }
};
