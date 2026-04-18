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
        Schema::create('customer_behavioral_baselines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->unique();
            $table->json('currency_codes')->nullable();
            $table->decimal('avg_transaction_size_myr', 18, 4)->default(0);
            $table->decimal('avg_transaction_frequency', 8, 2)->default(0);
            $table->json('preferred_counter_ids')->nullable();
            $table->string('registered_location')->nullable();
            $table->timestamp('last_calculated_at')->nullable();
            $table->integer('baseline_version')->default(1);
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_behavioral_baselines');
    }
};
