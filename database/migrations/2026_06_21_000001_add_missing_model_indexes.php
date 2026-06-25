<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * List of indexes to ensure exist.
     * Each entry: table => [columns] (single or composite)
     */
    private array $indexes = [
        'accounting_periods' => ['closed_by', 'fiscal_year_id'],
        'alerts' => ['case_id', 'flagged_transaction_id'],
        'backup_logs' => ['user_id'],
        'bank_reconciliations' => ['created_by', 'matched_to_journal_entry_id'],
        'budgets' => ['created_by'],
        'compliance_case_documents' => ['verified_by', 'uploaded_by', 'case_id'],
        'compliance_case_links' => ['case_id'],
        'compliance_case_notes' => ['author_id', 'case_id'],
        'compliance_cases' => ['primary_finding_id', 'primary_flag_id'],
        'cost_centers' => ['department_id'],
        'customer_documents' => ['uploaded_by'],
        'customer_relations' => ['related_customer_id'],
        'customer_risk_history' => ['assessed_by'],
        'edd_document_requests' => ['edd_record_id'],
        'edd_templates' => ['created_by'],
        'enhanced_diligence_records' => ['approved_by', 'questionnaire_completed_by', 'reviewed_by', 'customer_id', 'flagged_transaction_id'],
        'exchange_rate_histories' => ['created_by'],
        'fiscal_years' => ['closed_by'],
        'flagged_transactions' => ['reviewed_by', 'customer_id'],
        'journal_entries' => ['period_id', 'approved_by', 'created_by', 'reversed_by', 'posted_by'],
        'report_runs' => ['generated_by', 'schedule_id'],
        'report_schedules' => ['created_by'],
        'revaluation_entries' => ['posted_by'],
        'sanction_import_logs' => ['user_id'],
        'sanction_lists' => ['uploaded_by'],
        'screening_results' => ['sanction_entry_id', 'transaction_id', 'customer_id'],
        'stock_transfer_items' => ['stock_transfer_id'],
        'stock_transfers' => ['hq_approved_by', 'branch_manager_approved_by'],
        'system_alerts' => ['acknowledged_by'],
        'teller_allocations' => ['approved_by'],
        'threshold_audits' => ['changed_by'],
        'till_balances' => ['closed_by', 'opened_by', 'teller_allocation_id'],
        'transaction_errors' => ['resolved_by'],
        'transaction_imports' => ['user_id'],
        'transaction_state_history' => ['user_id'],
    ];

    /**
     * Generate a short, deterministic index name to avoid identifier length limits.
     * Format: idx_<md16 hash of table|col1|col2|...>
     */
    private function makeIndexName(string $table, array $columns): string
    {
        $key = $table.'|'.implode('|', $columns);

        return 'idx_'.substr(md5($key), 0, 12);
    }

    /**
     * Check whether an index with the given name exists on the table.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $result = DB::select('PRAGMA index_list('.$table.')', []);
            foreach ($result as $row) {
                if (isset($row->name) && $row->name === $indexName) {
                    return true;
                }
            }

            return false;
        }

        $database = DB::connection()->getDatabaseName();
        $result = DB::select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $indexName]
        );

        return count($result) > 0;
    }

    public function up(): void
    {
        foreach ($this->indexes as $table => $columns) {
            // Skip if any column does not exist in the table
            foreach ($columns as $col) {
                if (! Schema::hasColumn($table, $col)) {
                    continue 2;
                }
            }

            $indexName = $this->makeIndexName($table, $columns);
            if ($this->indexExists($table, $indexName)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($columns, $indexName) {
                $t->index($columns, $indexName);
            });
        }
    }

    public function down(): void
    {
        // Indexes are considered permanent; rollback not implemented.
    }
};
