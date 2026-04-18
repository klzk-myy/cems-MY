<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->date('entry_date')->nullable(false);
            $table->string('reference_type', 50)->nullable(false);
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('description')->nullable(false);
            $table->enum('status', ['Draft', 'Pending', 'Posted', 'Reversed'])->default('Posted');
            $table->foreignId('posted_by')->nullable(false)->constrained('users');
            $table->timestamp('posted_at')->useCurrent();
            $table->foreignId('reversed_by')->nullable()->constrained('users');
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();

            $table->index('entry_date');
            $table->index(['reference_type', 'reference_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
