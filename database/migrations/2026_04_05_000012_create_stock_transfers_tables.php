<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number')->unique();
            $table->enum('type', ['Standard', 'Emergency', 'Scheduled', 'Return']);
            $table->enum('status', [
                'Requested',
                'BranchManagerApproved',
                'HQApproved',
                'InTransit',
                'PartiallyReceived',
                'Completed',
                'Cancelled',
                'Rejected',
            ])->default('Requested');
            $table->string('source_branch_name')->nullable();
            $table->string('destination_branch_name')->nullable();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('requested_at')->nullable();
            $table->foreignId('branch_manager_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('branch_manager_approved_at')->nullable();
            $table->foreignId('hq_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('hq_approved_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->decimal('total_value_myr', 15, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'type']);
            $table->index('requested_by');
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->string('currency_code');
            $table->decimal('quantity', 18, 4);
            $table->decimal('rate', 15, 6);
            $table->decimal('value_myr', 15, 2);
            $table->decimal('quantity_received', 18, 4)->default(0);
            $table->decimal('quantity_in_transit', 18, 4)->default(0);
            $table->text('variance_notes')->nullable();
            $table->timestamps();

            $table->index('currency_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
    }
};
