<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('screening_results', function (Blueprint $table) {
            $table->string('source', 32)->default('sanctions')->after('match_type');
            $table->foreignId('adverse_media_entry_id')
                ->nullable()
                ->after('sanction_entry_id')
                ->constrained('adverse_media_entries')
                ->nullOnDelete();
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::table('screening_results', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropConstrainedForeignId('adverse_media_entry_id');
            $table->dropColumn('source');
        });
    }
};
