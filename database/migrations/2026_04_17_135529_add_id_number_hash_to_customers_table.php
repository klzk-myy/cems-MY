<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('id_number_hash', 64)->nullable()->after('id_number_encrypted');
            $table->index('id_number_hash');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['id_number_hash']);
            $table->dropColumn('id_number_hash');
        });
    }
};
