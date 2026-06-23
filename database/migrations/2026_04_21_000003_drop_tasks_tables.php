<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('approval_tasks');
    }

    public function down(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assigned_to')->constrained('users');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('completed_by')->nullable()->constrained('users');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category');
            $table->string('priority');
            $table->string('status')->default('pending');
            $table->timestamp('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('transaction_id')->nullable()->constrained();
            $table->foreignId('customer_id')->nullable()->constrained();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['assigned_to', 'status']);
            $table->index(['category', 'priority']);
            $table->index('due_date');
        });

        Schema::create('approval_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained();
            $table->foreignId('approver_id')->constrained('users');
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['transaction_id', 'status']);
            $table->index(['approver_id', 'status']);
        });
    }
};
