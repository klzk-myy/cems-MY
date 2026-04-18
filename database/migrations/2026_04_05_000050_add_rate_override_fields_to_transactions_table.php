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
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('base_rate', 20)->nullable()->after('rate');
            $table->boolean('rate_override')->default(false)->after('base_rate');
            $table->integer('rate_override_approved_by')->nullable()->after('rate_override');
            $table->timestamp('rate_override_approved_at')->nullable()->after('rate_override_approved_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['base_rate', 'rate_override', 'rate_override_approved_by', 'rate_override_approved_at']);
        });
    }
};
