<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('till_id', 50)->default('MAIN');
            $table->enum('type', ['Buy', 'Sell']);
            $table->string('currency_code', 3);
            $table->decimal('amount_local', 18, 4);
            $table->decimal('amount_foreign', 18, 4);
            $table->decimal('rate', 18, 6);
            $table->text('purpose')->nullable();
            $table->string('source_of_funds', 255)->nullable();
            $table->enum('status', [
                'Draft',
                'PendingApproval',
                'Approved',
                'Processing',
                'Completed',
                'Finalized',
                'Cancelled',
                'Reversed',
                'Failed',
                'Rejected',
                'Pending',
                'OnHold',
                'PendingCancellation',
            ])->default('Draft');
            $table->text('hold_reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->enum('cdd_level', ['Simplified', 'Standard', 'Enhanced']);
            $table->timestamps();
            $table->index(['customer_id', 'created_at']);
            $table->index('status');
            $table->index(['type', 'currency_code']);
            $table->index('created_at');
            $table->index('amount_local');
            $table->foreign('currency_code')->references('code')->on('currencies');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
