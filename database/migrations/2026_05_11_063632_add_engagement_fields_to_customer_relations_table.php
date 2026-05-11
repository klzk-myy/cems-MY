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
        Schema::table('customer_relations', function (Blueprint $table) {
            $table->string('engagement_level')->nullable();
            $table->text('engagement_notes')->nullable();
            $table->timestamp('engagement_assessed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_relations', function (Blueprint $table) {
            //
        });
    }
};
