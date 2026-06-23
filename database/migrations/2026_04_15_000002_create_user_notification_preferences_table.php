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
        Schema::create('user_notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');
            $table->string('notification_type', 100);
            $table->boolean('email_enabled')->default(true);
            $table->boolean('sms_enabled')->default(false);
            $table->boolean('in_app_enabled')->default(true);
            $table->boolean('push_enabled')->default(false);
            $table->string('webhook_url')->nullable();
            $table->json('custom_settings')->nullable();
            $table->timestamps();

            // Ensure unique preferences per user per notification type
            $table->unique(['user_id', 'notification_type'], 'unique_user_notification_type');

            // Index for querying preferences by notification type
            $table->index('notification_type', 'idx_notification_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_notification_preferences');
    }
};
