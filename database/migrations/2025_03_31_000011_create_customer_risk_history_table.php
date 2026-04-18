<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_risk_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->integer('old_score')->nullable();
            $table->integer('new_score');
            $table->enum('old_rating', ['Low', 'Medium', 'High'])->nullable();
            $table->enum('new_rating', ['Low', 'Medium', 'High']);
            $table->text('change_reason');
            $table->foreignId('assessed_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_risk_history');
    }
};
