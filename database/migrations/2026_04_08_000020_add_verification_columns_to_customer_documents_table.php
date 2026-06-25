<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_documents', function (Blueprint $table) {
            $table->unsignedBigInteger('verified_by')->nullable()->after('uploaded_by');
            $table->foreign('verified_by')->references('id')->on('users')->nullOnDelete();
            $table->dateTime('verified_at')->nullable()->after('verified_by');
            $table->date('expiry_date')->nullable()->after('verified_at');
            $table->string('status')->default('pending')->after('expiry_date');
        });
    }

    public function down(): void
    {
        Schema::table('customer_documents', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
            $table->dropColumn(['verified_by', 'verified_at', 'expiry_date', 'status']);
        });
    }
};
