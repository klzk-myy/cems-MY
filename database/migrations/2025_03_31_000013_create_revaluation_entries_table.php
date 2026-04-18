<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revaluation_entries', function (Blueprint $table) {
            $table->id();
            $table->string('currency_code', 3);
            $table->string('till_id', 50)->default('MAIN');
            $table->decimal('old_rate', 18, 6);
            $table->decimal('new_rate', 18, 6);
            $table->decimal('position_amount', 18, 4);
            $table->decimal('gain_loss_amount', 18, 4);
            $table->date('revaluation_date');
            $table->foreignId('posted_by')->constrained('users');
            $table->timestamp('posted_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamps();
            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->index(['currency_code', 'revaluation_date']);
            $table->index('posted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revaluation_entries');
    }
};
