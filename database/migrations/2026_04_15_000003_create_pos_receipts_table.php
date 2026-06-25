<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->onDelete('cascade');
            $table->string('receipt_number')->unique();
            $table->enum('receipt_type', ['thermal', 'pdf']);
            $table->string('template_type');
            $table->json('receipt_data');
            $table->timestamp('printed_at')->nullable();
            $table->foreignId('printed_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->index('transaction_id');
            $table->index('receipt_number');
            $table->index('receipt_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_receipts');
    }
};
