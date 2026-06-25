<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sanction_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('sanction_entries', 'reference_number')) {
                $table->string('reference_number', 100)->nullable()->after('date_of_birth');
            }
            if (! Schema::hasColumn('sanction_entries', 'listing_date')) {
                $table->date('listing_date')->nullable()->after('reference_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sanction_entries', function (Blueprint $table) {
            if (Schema::hasColumn('sanction_entries', 'reference_number')) {
                $table->dropColumn('reference_number');
            }
            if (Schema::hasColumn('sanction_entries', 'listing_date')) {
                $table->dropColumn('listing_date');
            }
        });
    }
};
