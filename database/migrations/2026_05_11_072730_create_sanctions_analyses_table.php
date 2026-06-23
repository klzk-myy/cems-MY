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
        Schema::create('sanctions_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->string('analysis_type');
            $table->integer('transaction_count')->default(0);
            $table->decimal('total_amount', 20, 4)->default(0);
            $table->timestamp('analyzed_at')->useCurrent();
            $table->timestamps();

            $table->index('customer_id');
            $table->index('analysis_type');
            $table->index('analyzed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sanctions_analyses');
    }
};
