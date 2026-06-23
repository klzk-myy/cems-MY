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
        if (! Schema::hasTable('approval_tasks')) {
            Schema::create('approval_tasks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('transaction_id');
                $table->unsignedBigInteger('approver_id')->nullable();
                $table->enum('status', ['pending', 'approved', 'rejected', 'expired'])->default('pending');
                $table->decimal('threshold_amount', 18, 4)->notNull();
                $table->string('required_role', 50)->notNull();
                $table->text('notes')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('decided_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();

                $table->foreign('transaction_id')
                    ->references('id')
                    ->on('transactions')
                    ->onDelete('cascade');

                $table->foreign('approver_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('set null');

                $table->index('transaction_id');
                $table->index('approver_id');
                $table->index('status');
                $table->index('expires_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_tasks');
    }
};
