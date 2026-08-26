<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adverse_media_entries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('normalized_name')->nullable();
            $table->string('alias')->nullable();
            $table->string('article_title');
            $table->string('source');
            $table->string('url')->nullable();
            $table->text('snippet')->nullable();
            $table->date('published_at')->nullable();
            $table->enum('severity', ['low', 'medium', 'high'])->default('medium');
            $table->boolean('is_active')->default(true);
            $table->string('record_hash')->unique();
            $table->timestamps();

            $table->index('name');
            $table->index(['is_active', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adverse_media_entries');
    }
};
