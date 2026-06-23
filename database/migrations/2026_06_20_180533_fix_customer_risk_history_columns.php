<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * This migration was previously a destructive rename that changed
     * old_score/old_rating/assessed_by to previous_score/previous_rating/changed_by
     * and added changed_at. It has been converted to a no-op so environments that
     * have not yet run it skip the incorrect change. The authoritative schema is
     * defined in 2025_03_31_000011_create_customer_risk_history_table.php.
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
