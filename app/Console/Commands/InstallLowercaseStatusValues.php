<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Normalize persisted status values to lowercase_snake on an existing
 * (non-destructive) database. SchemaSeeder only runs on fresh installs,
 * so databases seeded while the enums carried TitleCase values still
 * store 'Completed', 'Under_Review', 'PendingApproval', etc. — values
 * the normalized enum casts can no longer resolve.
 *
 * ENUM columns are bridged through VARCHAR before remapping and then
 * re-narrowed: an ENUM cannot hold the old and new vocabularies at
 * once because case-insensitive collations treat 'Open' and 'open'
 * as the same member (MySQL error 1291). Varchar columns are updated
 * in place. Idempotent — safe to re-run.
 */
class InstallLowercaseStatusValues extends Command
{
    protected $signature = 'statuses:install-lowercase-values';

    protected $description = 'Remap legacy TitleCase status values to lowercase_snake across status columns';

    /**
     * table => column => [legacy value => new value]
     *
     * @var array<string, array<string, array<string, string>>>
     */
    protected const COLUMNS = [
        'transactions' => [
            'status' => [
                'Draft' => 'draft', 'PendingApproval' => 'pending_approval', 'Approved' => 'approved',
                'Processing' => 'processing', 'Completed' => 'completed', 'Finalized' => 'finalized',
                'Cancelled' => 'cancelled', 'Reversed' => 'reversed', 'Failed' => 'failed',
                'Rejected' => 'rejected', 'PendingCancellation' => 'pending_cancellation',
                'Pending' => 'pending', 'OnHold' => 'on_hold',
            ],
        ],
        'journal_entries' => [
            'status' => [
                'Draft' => 'draft', 'Pending' => 'pending', 'Posted' => 'posted',
                'Rejected' => 'rejected', 'Reversed' => 'reversed',
            ],
        ],
        'flagged_transactions' => [
            'status' => [
                'Open' => 'open', 'Under_Review' => 'under_review', 'Resolved' => 'resolved',
                'Escalated' => 'escalated', 'Rejected' => 'rejected',
            ],
        ],
        'alerts' => [
            'status' => [
                'Open' => 'open', 'Under_Review' => 'under_review', 'Resolved' => 'resolved',
                'Escalated' => 'escalated', 'Rejected' => 'rejected',
            ],
        ],
        'compliance_cases' => [
            'status' => [
                'Open' => 'open', 'UnderReview' => 'under_review', 'PendingApproval' => 'pending_approval',
                'Closed' => 'closed', 'Escalated' => 'escalated',
            ],
        ],
        'compliance_findings' => [
            'status' => [
                'New' => 'new', 'Reviewed' => 'reviewed', 'Dismissed' => 'dismissed',
                'Case_Created' => 'case_created',
            ],
        ],
        'fiscal_years' => [
            'status' => [
                'Draft' => 'draft', 'Open' => 'open', 'Closed' => 'closed',
                'Archived' => 'archived', 'Deleted' => 'deleted',
            ],
        ],
        'accounting_periods' => [
            'status' => [
                'Open' => 'open', 'Closed' => 'closed', 'Locked' => 'locked',
            ],
        ],
        'enhanced_diligence_records' => [
            'status' => [
                'Incomplete' => 'incomplete', 'Pending_Questionnaire' => 'pending_questionnaire',
                'Questionnaire_Submitted' => 'questionnaire_submitted', 'Pending_Review' => 'pending_review',
                'Approved' => 'approved', 'Rejected' => 'rejected', 'Expired' => 'expired',
            ],
        ],
        'edd_document_requests' => [
            'status' => [
                'Pending' => 'pending', 'Received' => 'received',
                'Verified' => 'verified', 'Rejected' => 'rejected',
            ],
        ],
        'reports_generated' => [
            'status' => [
                'Pending' => 'pending', 'Generated' => 'generated', 'Failed' => 'failed',
                'Submitted' => 'submitted', 'Archived' => 'archived',
            ],
        ],
        'stock_transfers' => [
            'status' => [
                'Requested' => 'requested', 'BranchManagerApproved' => 'branch_manager_approved',
                'HqApproved' => 'hq_approved', 'InTransit' => 'in_transit',
                'PartiallyReceived' => 'partially_received', 'Received' => 'received',
                'Completed' => 'completed', 'Cancelled' => 'cancelled', 'Rejected' => 'rejected',
            ],
        ],
        'str_reports' => [
            'status' => [
                'Draft' => 'draft', 'Submitted' => 'submitted',
                'Acknowledged' => 'acknowledged', 'Rejected' => 'rejected',
            ],
        ],
        'pool_remittances' => [
            'status' => [
                'Pending' => 'pending', 'Acknowledged' => 'acknowledged', 'Cancelled' => 'cancelled',
            ],
        ],
    ];

    public function handle(): int
    {
        $total = 0;

        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                $this->warn("{$table} does not exist — skipping.");

                continue;
            }

            foreach ($columns as $column => $map) {
                if (! Schema::hasColumn($table, $column)) {
                    $this->warn("{$table}.{$column} does not exist — skipping.");

                    continue;
                }

                $newValues = array_values(array_unique($map));
                $wasEnum = $this->isMysqlEnum($table, $column);

                if ($wasEnum) {
                    $this->bridgeEnumToVarchar($table, $column);
                }

                $updated = 0;
                foreach ($map as $legacy => $new) {
                    $updated += DB::table($table)->where($column, $legacy)->update([$column => $new]);
                }

                if ($wasEnum) {
                    $unexpected = DB::table($table)
                        ->whereNotIn($column, $newValues)
                        ->distinct()
                        ->pluck($column);

                    if ($unexpected->isNotEmpty()) {
                        $this->warn("{$table}.{$column}: value(s) outside the new vocabulary remain [{$unexpected->implode(', ')}] — column left as VARCHAR; resolve the rows and re-run.");
                    } else {
                        $this->alterEnum($table, $column, $newValues, $map);
                    }
                } else {
                    $this->normalizeColumnDefault($table, $column, $map);
                }

                $total += $updated;
                $this->line("{$table}.{$column}: {$updated} row(s) remapped.");
            }
        }

        $this->info("Status value normalization complete ({$total} rows updated).");

        return self::SUCCESS;
    }

    protected function isMysqlEnum(string $table, string $column): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        $definition = DB::selectOne("SHOW COLUMNS FROM {$table} WHERE Field = '{$column}'");

        return str_starts_with(strtolower($definition->Type ?? ''), 'enum(');
    }

    /**
     * Relax an ENUM column to VARCHAR so legacy and normalized values can
     * coexist during remapping. Preserves the column's nullability and
     * default; the column is re-narrowed by alterEnum() afterwards.
     */
    protected function bridgeEnumToVarchar(string $table, string $column): void
    {
        $columnMeta = DB::selectOne("SHOW COLUMNS FROM {$table} WHERE Field = '{$column}'");
        $default = $columnMeta->Default ?? null;
        $nullClause = ($columnMeta->Null ?? 'NO') === 'YES' ? ' NULL' : ' NOT NULL';
        $defaultClause = $default !== null ? ' DEFAULT '.$this->quote($default) : '';

        DB::statement("ALTER TABLE {$table} MODIFY {$column} VARCHAR(64){$nullClause}{$defaultClause}");
    }

    /**
     * Fix a varchar column's stored default when it still holds a legacy
     * value or has stray quotes baked in (e.g. '''Open''' written by an
     * older schema revision). Row remapping alone leaves the default
     * producing unresolvable enum values on default-inserted rows.
     *
     * @param  array<string, string>  $map
     */
    protected function normalizeColumnDefault(string $table, string $column, array $map): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $columnMeta = DB::selectOne("SHOW COLUMNS FROM {$table} WHERE Field = '{$column}'");
        $rawDefault = $columnMeta->Default ?? null;

        if ($rawDefault === null) {
            return;
        }

        $stripped = trim($rawDefault, "'");
        $translated = $map[$stripped] ?? $stripped;

        if ($stripped === $rawDefault && $translated === $stripped) {
            return;
        }

        $nullClause = ($columnMeta->Null ?? 'NO') === 'YES' ? ' NULL' : ' NOT NULL';
        DB::statement("ALTER TABLE {$table} MODIFY {$column} {$columnMeta->Type}{$nullClause} DEFAULT ".$this->quote($translated));
    }

    /**
     * @param  array<int, string>  $values
     * @param  array<string, string>  $map
     */
    protected function alterEnum(string $table, string $column, array $values, array $map = []): void
    {
        $columnMeta = DB::selectOne("SHOW COLUMNS FROM {$table} WHERE Field = '{$column}'");
        $rawDefault = $columnMeta->Default ?? null;
        $default = $rawDefault !== null ? ($map[$rawDefault] ?? $rawDefault) : null;
        $nullClause = ($columnMeta->Null ?? 'NO') === 'YES' ? ' NULL' : ' NOT NULL';
        $defaultClause = $default !== null && in_array($default, $values, true)
            ? ' DEFAULT '.$this->quote($default)
            : '';

        if ($rawDefault !== null && $defaultClause === '') {
            $this->warn("{$table}.{$column}: stored default '{$rawDefault}' is not in the new vocabulary — the DEFAULT clause is being dropped.");
        }

        $quoted = $this->quoteValues($values);
        DB::statement("ALTER TABLE {$table} MODIFY {$column} ENUM({$quoted}){$nullClause}{$defaultClause}");
    }

    /**
     * @param  array<int, string>  $values
     */
    protected function quoteValues(array $values): string
    {
        return implode(',', array_map(fn (string $v) => $this->quote($v), array_unique($values)));
    }

    protected function quote(string $value): string
    {
        return "'".str_replace("'", "''", $value)."'";
    }
}
