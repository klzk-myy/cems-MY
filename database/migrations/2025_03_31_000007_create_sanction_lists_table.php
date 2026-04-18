<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanction_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->enum('list_type', ['UNSCR', 'MOHA', 'Internal']);
            $table->string('source_file', 255)->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->boolean('is_active')->default(true);
            $table->timestamp('uploaded_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->index('list_type');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanction_lists');
    }
};
