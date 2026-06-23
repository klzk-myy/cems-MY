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
        if (! Schema::hasTable('transaction_errors')) {
            Schema::create('transaction_errors', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('transaction_id');
                $table->string('error_type', 100)->notNull();
                $table->text('error_message')->notNull();
                $table->json('error_context')->nullable();
                $table->unsignedTinyInteger('retry_count')->default(0);
                $table->unsignedTinyInteger('max_retries')->default(3);
                $table->timestamp('next_retry_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->unsignedBigInteger('resolved_by')->nullable();
                $table->text('resolution_notes')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();

                $table->foreign('transaction_id')
                    ->references('id')
                    ->on('transactions')
                    ->onDelete('cascade');

                $table->foreign('resolved_by')
                    ->references('id')
                    ->on('users')
                    ->onDelete('set null');

                $table->index('transaction_id');
                $table->index('error_type');
                $table->index('retry_count');
                $table->index('resolved_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_errors');
    }
};
