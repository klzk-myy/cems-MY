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
        Schema::table('customers', function (Blueprint $table) {
            $table->string('closure_reason')->nullable()->after('rejection_reason');
            $table->timestamp('closed_at')->nullable()->after('closure_reason');
            $table->timestamp('dormant_at')->nullable()->after('closed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['closure_reason', 'closed_at', 'dormant_at']);
        });
    }
};
