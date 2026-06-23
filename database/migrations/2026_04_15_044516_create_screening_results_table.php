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
        Schema::create('screening_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained();
            $table->foreignId('transaction_id')->nullable()->constrained();
            $table->string('screened_name');
            $table->foreignId('sanction_entry_id')->nullable()->constrained('sanction_entries');
            $table->enum('match_type', ['exact', 'levenshtein', 'soundex', 'metaphone', 'token']);
            $table->decimal('match_score', 5, 2);
            $table->enum('action_taken', ['clear', 'flag', 'block']);
            $table->enum('result', ['clear', 'flag', 'block']);
            $table->json('matched_fields')->nullable();
            $table->timestamps();
            $table->index(['result', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('screening_results');
    }
};
