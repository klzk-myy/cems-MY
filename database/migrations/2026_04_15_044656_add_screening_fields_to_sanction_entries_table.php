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
            $table->string('normalized_name')->nullable()->after('entity_name');
            $table->string('soundex_code', 10)->nullable()->after('normalized_name');
            $table->string('metaphone_code', 20)->nullable()->after('soundex_code');
            $table->string('status')->default('active')->after('metaphone_code');
            $table->index('normalized_name');
            $table->index('soundex_code');
            $table->index('metaphone_code');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sanction_entries', function (Blueprint $table) {
            $table->dropIndex(['normalized_name']);
            $table->dropIndex(['soundex_code']);
            $table->dropIndex(['metaphone_code']);
            $table->dropIndex(['status']);
            $table->dropColumn(['normalized_name', 'soundex_code', 'metaphone_code', 'status']);
        });
    }
};
