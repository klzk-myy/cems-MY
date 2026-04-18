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
        Schema::table('reports_generated', function (Blueprint $table) {
            $table->enum('status', ['Generated', 'Submitted', 'Pending'])->default('Generated')->after('file_format');
            $table->timestamp('submitted_at')->nullable()->after('status');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->after('submitted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reports_generated', function (Blueprint $table) {
            $table->dropForeign(['submitted_by']);
            $table->dropColumn(['status', 'submitted_at', 'submitted_by']);
        });
    }
};
