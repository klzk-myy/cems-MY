<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('counters', function (Blueprint $table) {
            $table->foreignId('assigned_teller_id')->nullable()->after('branch_id')->constrained('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('counters', function (Blueprint $table) {
            $table->dropForeign(['assigned_teller_id']);
            $table->dropColumn('assigned_teller_id');
        });
    }
};
