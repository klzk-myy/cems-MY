<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The customer_risk_history table already uses the canonical column names
     * defined in the original migration (2025_03_31_000011_create_customer_risk_history_table):
     * old_score, old_rating, and assessed_by. This migration is kept as a marker
     * to avoid re-running a previous incorrect rename.
     */
    public function up(): void
    {
        // No changes needed; model and factory now align with the original schema.
    }

    public function down(): void
    {
        // No changes needed.
    }
};
