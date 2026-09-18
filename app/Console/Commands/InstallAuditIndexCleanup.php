<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Drop redundant/duplicate indexes on an existing (non-destructive)
 * database. SchemaSeeder only runs on fresh installs, so this command is
 * the supported upgrade path for live databases. Idempotent — safe to
 * re-run.
 *
 * Every drop is guarded twice: the "covering" index that makes the
 * candidate redundant must exist, AND the candidate's actual column list
 * must match what we expect to drop — so a same-named index with a
 * different definition (e.g. alerts_composite_idx, which is a 3-column
 * composite on fresh installs) is never touched.
 */
class InstallAuditIndexCleanup extends Command
{
    protected $signature = 'db:install-audit-index-cleanup {--force : Run without interactive confirmation}';

    protected $description = 'Drop duplicate indexes that have a surviving covering index';

    /**
     * table => [ [ dropIndex, expectedColumns, coveringIndexThatMustExist ], ... ]
     *
     * @var array<string, array<int, array{0: string, 1: string, 2: string}>>
     */
    private const DROPS = [
        'transactions' => [
            ['transactions_branch_created_idx', 'branch_id,created_at', 'transactions_branch_created'],
            ['transactions_branch_id_created_at_index', 'branch_id,created_at', 'transactions_branch_created'],
            ['transactions_branch_id_fk_index', 'branch_id', 'transactions_branch_id_index'],
            ['transactions_customer_id_index', 'customer_id', 'transactions_customer_created_idx'],
            ['transactions_customer_id_created_at_index', 'customer_id,created_at', 'transactions_customer_created_idx'],
            ['transactions_status_index', 'status', 'transactions_status_created_idx'],
            ['transactions_user_id_index', 'user_id', 'transactions_user_created_idx'],
        ],
        'flagged_transactions' => [
            ['flagged_transactions_status_date_idx', 'status,created_at', 'flagged_transactions_status_created_idx'],
            ['idx_flagged_transactions_status_date', 'status,created_at', 'flagged_transactions_status_created_idx'],
            ['flagged_transactions_flag_type_date_idx', 'flag_type,created_at', 'flagged_transactions_flag_type_created_idx'],
            ['flagged_transactions_status_flag_type_index', 'status,flag_type', 'flagged_transactions_status_flag_type'],
            ['flagged_transactions_flag_type_index', 'flag_type', 'flagged_transactions_flag_type_created_idx'],
            ['flagged_trans_flag_type_idx', 'flag_type', 'flagged_transactions_flag_type_created_idx'],
            ['flagged_transactions_status_index', 'status', 'flagged_transactions_status_created_idx'],
            ['flagged_trans_status_idx', 'status', 'flagged_transactions_status_created_idx'],
        ],
        'currency_positions' => [
            ['currency_positions_currency_idx', 'currency_code', 'currency_positions_currency_code_index'],
        ],
        'account_ledger' => [
            ['idx_account_ledger_account_entry', 'account_code,entry_date', 'account_ledger_account_code_entry_date_index'],
            ['idx_account_ledger_journal_entry', 'journal_entry_id', 'account_ledger_journal_entry_id_index'],
        ],
        'system_logs' => [
            ['idx_system_logs_action', 'action', 'idx_system_logs_action_date'],
            ['idx_system_logs_severity', 'severity', 'idx_system_logs_severity_date'],
            ['idx_system_logs_entity_type', 'entity_type', 'system_logs_entity_type_index'],
            ['system_logs_user_id_index', 'user_id', 'system_logs_user_action_created_idx'],
            ['system_logs_user_id_action_index', 'user_id,action', 'system_logs_user_action_created_idx'],
        ],
        'journal_entries' => [
            ['idx_journal_entries_period_id', 'period_id', 'idx_journal_entries_period_status'],
        ],
        'counter_sessions' => [
            ['counter_sessions_user_id_index', 'user_id', 'counter_sessions_user_date_idx'],
        ],
        'alerts' => [
            ['alerts_priority_status', 'priority,status', 'alerts_priority_status_index'],
            ['alerts_composite_idx', 'status', 'alerts_status_index'],
        ],
        'branches' => [
            ['branches_code_index', 'code', 'branches_code_unique'],
        ],
        'branch_pools' => [
            ['branch_pools_branch_id_currency_code_index', 'branch_id,currency_code', 'branch_pools_branch_id_currency_code_unique'],
        ],
        'compliance_cases' => [
            ['compliance_cases_case_number_idx', 'case_number', 'compliance_cases_case_number_unique'],
        ],
        'customer_risk_profiles' => [
            ['customer_risk_profiles_risk_tier_idx', 'risk_tier', 'customer_risk_profiles_risk_tier_index'],
        ],
        'enhanced_diligence_records' => [
            ['enhanced_diligence_records_edd_reference_index', 'edd_reference', 'enhanced_diligence_records_edd_reference_unique'],
        ],
        'transaction_confirmations' => [
            ['transaction_confirmations_confirmation_token_index', 'confirmation_token', 'transaction_confirmations_confirmation_token_unique'],
        ],
    ];

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Drop redundant indexes on '.DB::getDatabaseName().'?')) {
            $this->warn('Aborted.');

            return self::FAILURE;
        }

        foreach (self::DROPS as $table => $drops) {
            foreach ($drops as [$indexName, $expectedColumns, $covering]) {
                $actual = $this->indexColumns($table, $indexName);

                if ($actual === null) {
                    continue;
                }

                if ($actual !== $expectedColumns) {
                    $this->warn("{$table}.{$indexName}: has columns ({$actual}), expected ({$expectedColumns}) — skipped");

                    continue;
                }

                if (! $this->indexExists($table, $covering)) {
                    $this->warn("{$table}.{$indexName}: covering index {$covering} missing — skipped");

                    continue;
                }

                DB::statement("ALTER TABLE {$table} DROP INDEX {$indexName}");
                $this->line("dropped {$table}.{$indexName}");
            }
        }

        $this->info('Audit index cleanup complete.');

        return self::SUCCESS;
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return $this->indexColumns($table, $indexName) !== null;
    }

    /**
     * Comma-joined column list of the index in position order, or null
     * when the index does not exist.
     */
    private function indexColumns(string $table, string $indexName): ?string
    {
        $row = DB::selectOne(
            'SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? GROUP BY INDEX_NAME',
            [DB::getDatabaseName(), $table, $indexName]
        );

        return $row?->cols;
    }
}
