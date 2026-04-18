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
        Schema::create('customer_risk_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->unique();
            $table->integer('risk_score')->default(20); // 0-100
            $table->string('risk_tier'); // Low/Medium/High/Critical
            $table->json('risk_factors')->nullable();
            $table->integer('previous_score')->nullable();
            $table->timestamp('score_changed_at')->nullable();
            $table->timestamp('next_scheduled_recalculation')->nullable();
            $table->string('recalculation_trigger')->nullable(); // RecalculationTrigger enum
            $table->timestamp('locked_until')->nullable();
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->string('lock_reason')->nullable();
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->index('risk_tier');
            $table->index('risk_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_risk_profiles');
    }
};
