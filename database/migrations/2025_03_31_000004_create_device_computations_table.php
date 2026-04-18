<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_computations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('device_name', 255)->nullable();  // User agent or custom name
            $table->string('device_fingerprint', 64);         // Hash of device identifiers
            $table->string('ip_address', 45)->nullable();      // IPv4 or IPv6
            $table->timestamp('expires_at')->nullable();      // When trusted device expires
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'device_fingerprint']);
            $table->index(['user_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_computations');
    }
};
