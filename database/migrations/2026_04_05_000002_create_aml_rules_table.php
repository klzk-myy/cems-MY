<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            // SQLite test runs use the consolidated AML rules migration instead.
            return;
        }

        Schema::create('aml_rules', function (Blueprint $table) {
            $table->id();
            $table->string('rule_code', 50)->unique();
            $table->string('rule_name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->string('flag_type', 50);
            $table->json('parameters')->nullable();
            $table->integer('priority')->default(100);
            $table->timestamps();
            $table->index('is_enabled');
            $table->index('priority');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aml_rules');
    }
};
