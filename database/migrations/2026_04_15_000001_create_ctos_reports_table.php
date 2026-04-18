<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ctos_reports')) {
            Schema::create('ctos_reports', function (Blueprint $table) {
                $table->id();
                $table->string('ctos_number', 20)->unique();
                $table->foreignId('transaction_id')->constrained()->onDelete('cascade');
                $table->string('branch_id', 50)->nullable();
                $table->foreignId('customer_id')->constrained()->onDelete('restrict');
                $table->string('customer_name', 255);
                $table->string('id_type', 20);
                $table->string('id_number_masked', 50);
                $table->date('date_of_birth');
                $table->string('nationality', 100);
                $table->decimal('amount_local', 18, 2);
                $table->decimal('amount_foreign', 18, 4);
                $table->string('currency_code', 3);
                $table->string('transaction_type', 10);
                $table->date('report_date');
                $table->enum('status', ['draft', 'submitted', 'acknowledged', 'rejected'])->default('draft');
                $table->timestamp('submitted_at')->nullable();
                $table->string('bnm_reference', 50)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamps();

                $table->index(['ctos_number']);
                $table->index(['transaction_id']);
                $table->index(['customer_id']);
                $table->index(['report_date']);
                $table->index(['status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ctos_reports');
    }
};
