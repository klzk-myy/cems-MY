<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add missing indexes to customers table for production performance.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Index for ID number lookups (KYC verification)
            // Note: id_number is encrypted, index on a hash column would be better
            // but for now we index what's queryable

            // Risk rating queries (compliance dashboard)
            if (! $this->hasIndex('customers', 'customers_risk_rating_index')) {
                $table->index('risk_rating', 'customers_risk_rating_index');
            }

            // PEP status queries
            if (! $this->hasIndex('customers', 'customers_pep_status_index')) {
                $table->index('pep_status', 'customers_pep_status_index');
            }

            // Active status for dropdowns
            if (! $this->hasIndex('customers', 'customers_is_active_index')) {
                $table->index('is_active', 'customers_is_active_index');
            }

            // Composite: risk rating + last transaction (inactive customer reports)
            if (! $this->hasIndex('customers', 'customers_risk_transaction_idx')) {
                $table->index(['risk_rating', 'last_transaction_at'], 'customers_risk_transaction_idx');
            }

            // Full name search (using prefix index for performance)
            if (! $this->hasIndex('customers', 'customers_full_name_index')) {
                $table->index('full_name', 'customers_full_name_index');
            }

            // Sanction hit queries
            if (! $this->hasIndex('customers', 'customers_sanction_hit_index')) {
                $table->index('sanction_hit', 'customers_sanction_hit_index');
            }

            // CDD level queries
            if (! $this->hasIndex('customers', 'customers_cdd_level_index')) {
                $table->index('cdd_level', 'customers_cdd_level_index');
            }

            // Nationality queries (for country risk analysis)
            if (! $this->hasIndex('customers', 'customers_nationality_index')) {
                $table->index('nationality', 'customers_nationality_index');
            }

            // Date of birth for age verification queries
            if (! $this->hasIndex('customers', 'customers_date_of_birth_index')) {
                $table->index('date_of_birth', 'customers_date_of_birth_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_risk_rating_index');
            $table->dropIndex('customers_pep_status_index');
            $table->dropIndex('customers_is_active_index');
            $table->dropIndex('customers_risk_transaction_idx');
            $table->dropIndex('customers_full_name_index');
            $table->dropIndex('customers_sanction_hit_index');
            $table->dropIndex('customers_cdd_level_index');
            $table->dropIndex('customers_nationality_index');
            $table->dropIndex('customers_date_of_birth_index');
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        $indexes = Schema::getIndexes($table);
        foreach ($indexes as $idx) {
            if ($idx['name'] === $index) {
                return true;
            }
        }

        return false;
    }
};
