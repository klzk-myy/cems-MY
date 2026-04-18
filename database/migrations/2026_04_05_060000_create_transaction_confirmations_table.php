<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained();
            $table->foreignId('user_id'); // User who requested confirmation
            $table->foreignId('confirmed_by')->nullable(); // User who confirmed
            $table->timestamp('confirmed_at')->nullable();
            $table->enum('status', ['pending', 'confirmed', 'rejected', 'expired'])->default('pending');
            $table->string('confirmation_token', 64)->unique()->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['transaction_id', 'status']);
            $table->index('confirmation_token');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_confirmations');
    }
};
