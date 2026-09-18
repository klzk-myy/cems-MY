<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Right-size varchar(255) columns on an existing database. SchemaSeeder
 * only runs on fresh installs, so this command is the supported upgrade
 * path for live databases. Idempotent — columns already at the target
 * type are skipped.
 *
 * Column definitions (nullability, default, collation) are read from
 * information_schema and restated in the MODIFY so no attribute is lost.
 * Foreign-key checks are disabled for the duration because
 * currencies.code / currency_code is resized as one coordinated set —
 * MySQL requires FK-referenced types to match exactly.
 */
class InstallAuditVarcharSizing extends Command
{
    protected $signature = 'db:install-audit-varchar-sizing {--force : Run without interactive confirmation}';

    protected $description = 'Right-size semantic varchar(255) columns to their real domain width';

    /**
     * table.column => target type.
     *
     * @var array<string, string>
     */
    private const TARGETS = [
        // ISO-4217 currency codes (FK group — all resized together)
        'currencies.code' => 'varchar(8)',
        'transactions.currency_code' => 'varchar(8)',
        'branch_pools.currency_code' => 'varchar(8)',
        'pool_remittances.currency_code' => 'varchar(8)',
        'teller_allocations.currency_code' => 'varchar(8)',
        'currency_positions.currency_code' => 'varchar(8)',
        'exchange_rate_histories.currency_code' => 'varchar(8)',
        'exchange_rates.currency_code' => 'varchar(8)',
        'revaluation_entries.currency_code' => 'varchar(8)',
        'stock_reservations.currency_code' => 'varchar(8)',
        'stock_transfer_items.currency_code' => 'varchar(8)',
        'till_balances.currency_code' => 'varchar(8)',
        // Branch/entity codes
        'branches.code' => 'varchar(16)',
        'counters.code' => 'varchar(16)',
        'departments.code' => 'varchar(32)',
        'cost_centers.code' => 'varchar(32)',
        'high_risk_countries.country_code' => 'varchar(8)',
        // Network + hash chain
        'audit_trails.ip_address' => 'varchar(45)',
        'device_computations.ip_address' => 'varchar(45)',
        'system_logs.ip_address' => 'varchar(45)',
        'system_logs.previous_hash' => 'varchar(128)',
        'system_logs.entry_hash' => 'varchar(128)',
        // Country/nationality names
        'branches.country' => 'varchar(64)',
        'sanction_entries.country' => 'varchar(64)',
        'customers.nationality' => 'varchar(64)',
        'customer_relations.nationality' => 'varchar(64)',
        'sanction_entries.nationality' => 'varchar(64)',
        // Polymorphic type columns (FQCN headroom)
        'audit_trails.auditable_type' => 'varchar(128)',
        'system_logs.entity_type' => 'varchar(128)',
        'notifications.type' => 'varchar(128)',
        'notifications.notifiable_type' => 'varchar(128)',
        // Status / enum-ish columns (max observed 38 chars)
        'branches.type' => 'varchar(64)',
        'branches.state' => 'varchar(64)',
        'accounting_periods.status' => 'varchar(64)',
        'compliance_findings.finding_type' => 'varchar(64)',
        'compliance_findings.severity' => 'varchar(64)',
        'compliance_findings.status' => 'varchar(64)',
        'customers.risk_rating' => 'varchar(64)',
        'transactions.type' => 'varchar(64)',
        'transactions.status' => 'varchar(64)',
        'flagged_transactions.severity' => 'varchar(64)',
        'compliance_cases.status' => 'varchar(64)',
        'compliance_cases.severity' => 'varchar(64)',
        'alerts.type' => 'varchar(64)',
        'alerts.status' => 'varchar(64)',
        'aml_rules.action' => 'varchar(64)',
        'audit_trails.action' => 'varchar(64)',
        'backup_logs.status' => 'varchar(64)',
        'branch_closure_workflows.status' => 'varchar(64)',
        'counters.status' => 'varchar(64)',
        'counter_sessions.status' => 'varchar(64)',
        'customer_documents.status' => 'varchar(64)',
        'customer_risk_profiles.risk_tier' => 'varchar(64)',
        'enhanced_diligence_records.status' => 'varchar(64)',
        'edd_document_requests.status' => 'varchar(64)',
        'edd_templates.type' => 'varchar(64)',
        'report_runs.status' => 'varchar(64)',
        'sanction_entries.status' => 'varchar(64)',
        'stock_reservations.status' => 'varchar(64)',
        'str_reports.status' => 'varchar(64)',
        'system_health_checks.check_name' => 'varchar(64)',
        'system_logs.action' => 'varchar(64)',
    ];

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Right-size varchar columns on '.DB::getDatabaseName().'?')) {
            $this->warn('Aborted.');

            return self::FAILURE;
        }

        // MariaDB blocks MODIFY on an FK-referenced column even with
        // FOREIGN_KEY_CHECKS=0 (error 1833), so the currencies.code group
        // is resized inside a drop/re-add window for its referencing FKs.
        $fks = $this->currencyCodeFks();

        foreach ($fks as $fk) {
            DB::statement("ALTER TABLE `{$fk->TABLE_NAME}` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
        }

        try {
            foreach (self::TARGETS as $key => $type) {
                [$table, $column] = explode('.', $key);
                $this->modifyColumn($table, $column, $type);
            }

            $this->alignRoleEnums();
        } finally {
            foreach ($fks as $fk) {
                DB::statement(
                    "ALTER TABLE `{$fk->TABLE_NAME}` ADD CONSTRAINT `{$fk->CONSTRAINT_NAME}` "
                    .'FOREIGN KEY (`currency_code`) REFERENCES `currencies` (`code`) '
                    ."ON DELETE {$fk->DELETE_RULE} ON UPDATE {$fk->UPDATE_RULE}"
                );
            }
        }

        $this->info('Audit varchar sizing complete.');

        return self::SUCCESS;
    }

    /**
     * users.role / role_permissions.role are ENUMs in SchemaSeeder but the
     * live database predates that change (varchar(255)). All live values
     * match the enum domain, so convert in place.
     */
    private function alignRoleEnums(): void
    {
        $enum = "enum('teller','manager','compliance_officer','accountant','admin')";

        foreach (['users.role', 'role_permissions.role'] as $key) {
            [$table, $column] = explode('.', $key);
            $this->modifyColumn($table, $column, $enum);
        }
    }

    /**
     * FK constraints that reference currencies.code, with their delete/
     * update rules for re-creation after the resize.
     *
     * @return array<int, object{TABLE_NAME: string, CONSTRAINT_NAME: string, DELETE_RULE: string, UPDATE_RULE: string}>
     */
    private function currencyCodeFks(): array
    {
        return DB::select(
            'SELECT k.TABLE_NAME, k.CONSTRAINT_NAME, r.DELETE_RULE, r.UPDATE_RULE '
            .'FROM information_schema.KEY_COLUMN_USAGE k '
            .'JOIN information_schema.REFERENTIAL_CONSTRAINTS r '
            .'ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME '
            .'AND r.TABLE_NAME = k.TABLE_NAME '
            ."WHERE k.TABLE_SCHEMA = ? AND k.REFERENCED_TABLE_NAME = 'currencies' "
            ."AND k.REFERENCED_COLUMN_NAME = 'code'",
            [DB::getDatabaseName()]
        );
    }

    private function modifyColumn(string $table, string $column, string $type): void
    {
        $meta = DB::selectOne(
            'SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, CHARACTER_SET_NAME, COLLATION_NAME '
            .'FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [DB::getDatabaseName(), $table, $column]
        );

        if ($meta === null) {
            $this->warn("{$table}.{$column}: not found — skipped");

            return;
        }

        if (strtolower($meta->COLUMN_TYPE) === $type) {
            return;
        }

        $sql = "ALTER TABLE `{$table}` MODIFY `{$column}` {$type}";

        if ($meta->CHARACTER_SET_NAME !== null) {
            $sql .= " CHARACTER SET {$meta->CHARACTER_SET_NAME} COLLATE {$meta->COLLATION_NAME}";
        }

        $sql .= $meta->IS_NULLABLE === 'YES' ? ' NULL' : ' NOT NULL';

        if ($meta->COLUMN_DEFAULT !== null) {
            // MariaDB returns varchar defaults already single-quoted
            // ('teller'); unwrap before re-quoting.
            $default = (string) preg_replace("/^'(.*)'$/s", '$1', $meta->COLUMN_DEFAULT);
            $default = str_replace(["\\'", "''"], "'", $default);
            $sql .= ' DEFAULT '.DB::getPdo()->quote($default);
        }

        DB::statement($sql);
        $this->line("resized {$table}.{$column} → {$type}");
    }
}
