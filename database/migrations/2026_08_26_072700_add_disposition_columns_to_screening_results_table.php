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
        Schema::table('screening_results', function (Blueprint $table) {
            $table->string('disposition')->nullable()->after('matched_fields');
            $table->text('disposition_reason')->nullable()->after('disposition');
            $table->foreignId('dispositioned_by')->nullable()->after('disposition_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('dispositioned_at')->nullable()->after('dispositioned_by');
            $table->index(['disposition', 'result']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('screening_results', function (Blueprint $table) {
            $table->dropIndex(['disposition', 'result']);
            $table->dropConstrainedForeignId('dispositioned_by');
            $table->dropColumn(['disposition', 'disposition_reason', 'dispositioned_at']);
        });
    }
};
