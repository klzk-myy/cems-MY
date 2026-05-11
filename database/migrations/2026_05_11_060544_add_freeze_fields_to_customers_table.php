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
            $table->boolean('is_frozen')->default(false)->after('sanction_hit');
            $table->string('freeze_reason')->nullable()->after('is_frozen');
            $table->timestamp('frozen_at')->nullable()->after('freeze_reason');
            $table->boolean('transactions_blocked')->default(false)->after('frozen_at');
            $table->string('rejection_reason')->nullable()->after('transactions_blocked');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            //
        });
    }
};
