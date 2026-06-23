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
        if (! Schema::hasTable('alerts')) {
            return;
        }

        Schema::table('alerts', function (Blueprint $table) {
            if (! Schema::hasColumn('alerts', 'status')) {
                $table->string('status', 30)->default('Open')->after('case_id');
                $table->index('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('alerts')) {
            return;
        }

        Schema::table('alerts', function (Blueprint $table) {
            if (Schema::hasColumn('alerts', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
