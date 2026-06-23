<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('related_customer_id')->nullable()->constrained('customers');
            $table->enum('relation_type', [
                'spouse', 'child', 'parent', 'sibling',
                'close_associate', 'business_partner',
                'beneficial_owner', 'director', 'signatory',
                'related_entity',
            ]);
            $table->string('related_name');
            $table->string('id_type')->nullable();
            $table->string('id_number_encrypted')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('nationality')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_pep')->default(false);
            $table->json('additional_info')->nullable();
            $table->timestamps();

            $table->index('customer_id');
            $table->index(['customer_id', 'relation_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_relations');
    }
};
