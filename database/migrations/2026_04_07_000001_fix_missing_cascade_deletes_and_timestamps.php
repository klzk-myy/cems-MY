<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add timestamps to sanction_lists
        Schema::table('sanction_lists', function (Blueprint $table) {
            $table->timestamps();
        });

        // Add timestamps to sanction_entries
        Schema::table('sanction_entries', function (Blueprint $table) {
            $table->timestamps();
        });

        if (DB::connection()->getDriverName() === 'sqlite') {
            // SQLite can't drop/recreate foreign keys via ALTER TABLE in Laravel.
            return;
        }

        // sanction_entries.list_id -> sanction_lists.id
        Schema::table('sanction_entries', function (Blueprint $table) {
            $table->dropForeign(['list_id']);
            $table->foreign('list_id')->references('id')->on('sanction_lists')->onDelete('cascade');
        });

        // customer_risk_history.customer_id -> customers.id
        Schema::table('customer_risk_history', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
        });

        // customer_documents.customer_id -> customers.id
        Schema::table('customer_documents', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
        });

        // enhanced_diligence_records.customer_id -> customers.id
        Schema::table('enhanced_diligence_records', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            // Best-effort rollback: timestamps are still removed below.
        } else {
            // Revert sanction_entries.list_id cascade
            Schema::table('sanction_entries', function (Blueprint $table) {
                $table->dropForeign(['list_id']);
                $table->foreign('list_id')->references('id')->on('sanction_lists')->onDelete('restrict');
            });

            // Revert customer_risk_history cascade
            Schema::table('customer_risk_history', function (Blueprint $table) {
                $table->dropForeign(['customer_id']);
                $table->foreign('customer_id')->references('id')->on('customers')->onDelete('restrict');
            });

            // Revert customer_documents cascade
            Schema::table('customer_documents', function (Blueprint $table) {
                $table->dropForeign(['customer_id']);
                $table->foreign('customer_id')->references('id')->on('customers')->onDelete('restrict');
            });

            // Revert enhanced_diligence_records cascade
            Schema::table('enhanced_diligence_records', function (Blueprint $table) {
                $table->dropForeign(['customer_id']);
                $table->foreign('customer_id')->references('id')->on('customers')->onDelete('restrict');
            });
        }

        // Remove timestamps from sanction_entries
        Schema::table('sanction_entries', function (Blueprint $table) {
            $table->dropTimestamps();
        });

        // Remove timestamps from sanction_lists
        Schema::table('sanction_lists', function (Blueprint $table) {
            $table->dropTimestamps();
        });
    }
};
