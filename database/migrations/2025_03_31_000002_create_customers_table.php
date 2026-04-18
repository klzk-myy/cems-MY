<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('full_name', 255);
            $table->enum('id_type', ['MyKad', 'Passport', 'Others']);
            $table->binary('id_number_encrypted');
            $table->string('nationality', 100);
            $table->date('date_of_birth');
            $table->text('address')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email', 255)->nullable();
            $table->boolean('pep_status')->default(false);
            $table->integer('risk_score')->default(0);
            $table->enum('risk_rating', ['Low', 'Medium', 'High'])->default('Low');
            $table->timestamp('risk_assessed_at')->nullable();
            $table->timestamp('last_transaction_at')->nullable();
            $table->timestamps();
            $table->index('id_type');
            $table->index('nationality');
            $table->index('pep_status');
            $table->index('risk_rating');
            $table->index('last_transaction_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
