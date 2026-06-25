<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sanction_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('sanction_entries', 'list_source')) {
                $table->string('list_source', 255)->nullable()->after('list_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sanction_entries', function (Blueprint $table) {
            if (Schema::hasColumn('sanction_entries', 'list_source')) {
                $table->dropColumn('list_source');
            }
        });
    }
};
