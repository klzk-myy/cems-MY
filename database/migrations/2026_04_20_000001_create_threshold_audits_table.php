<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('threshold_audits', function (Blueprint $table) {
            $table->id();
            $table->string('category', 50);
            $table->string('key', 100);
            $table->string('old_value', 255);
            $table->string('new_value', 255);
            $table->foreignId('changed_by')->nullable()->constrained('users');
            $table->string('change_reason', 500)->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();
            $table->index(['category', 'key']);
            $table->index('changed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('threshold_audits');
    }
};
