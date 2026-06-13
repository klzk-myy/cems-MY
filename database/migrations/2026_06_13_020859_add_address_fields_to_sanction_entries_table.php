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
            if (! Schema::hasColumn('sanction_entries', 'address')) {
                $table->text('address')->nullable()->after('listing_date');
            }

            if (! Schema::hasColumn('sanction_entries', 'city')) {
                $table->string('city', 255)->nullable()->after('address');
            }

            if (! Schema::hasColumn('sanction_entries', 'country')) {
                $table->string('country', 255)->nullable()->after('city');
            }

            if (! Schema::hasColumn('sanction_entries', 'postal_code')) {
                $table->string('postal_code', 255)->nullable()->after('country');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sanction_entries', function (Blueprint $table) {
            if (Schema::hasColumn('sanction_entries', 'postal_code')) {
                $table->dropColumn('postal_code');
            }

            if (Schema::hasColumn('sanction_entries', 'country')) {
                $table->dropColumn('country');
            }

            if (Schema::hasColumn('sanction_entries', 'city')) {
                $table->dropColumn('city');
            }

            if (Schema::hasColumn('sanction_entries', 'address')) {
                $table->dropColumn('address');
            }
        });
    }
};
