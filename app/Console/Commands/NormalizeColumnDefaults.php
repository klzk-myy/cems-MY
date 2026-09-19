<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Repair corrupted string column defaults on an existing (non-destructive)
 * database. Pre-SchemaSeeder revisions wrote defaults two wrong ways:
 *
 *  - quote characters baked into the stored default — the column default
 *    is the characters 'pending' including quotes, so default-inserted
 *    rows carry literal quotes;
 *  - the literal text 'NULL' as a default on a nullable column, so
 *    default-inserted rows carry the four-letter string instead of NULL.
 *
 * information_schema cannot distinguish these defects reliably (MariaDB
 * wraps healthy string defaults in quotes; an explicit DEFAULT NULL shows
 * as the text NULL), so candidates are re-read through SHOW COLUMNS,
 * whose Default field is the raw stored default: quoted bug defaults
 * appear with their quotes on, the NULL-text bug as the bare characters,
 * and a genuine DEFAULT NULL as a real NULL.
 * Idempotent — repaired defaults no longer match either defect pattern.
 */
class NormalizeColumnDefaults extends Command
{
    protected $signature = 'db:normalize-column-defaults';

    protected $description = 'Repair quote-embedded and literal-NULL string column defaults on an existing database';

    /**
     * String-ish types only — numeric/date/expression defaults never carry
     * this corruption and expression defaults must not be re-quoted.
     *
     * @var array<int, string>
     */
    protected const SCAN_TYPES = [
        'char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext', 'enum',
    ];

    public function handle(): int
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->info('Non-MySQL driver — column defaults do not carry this corruption.');

            return self::SUCCESS;
        }

        $candidates = DB::select(
            'SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND DATA_TYPE IN ('.$this->quoteValues(self::SCAN_TYPES).')
             AND COLUMN_DEFAULT IS NOT NULL
             ORDER BY TABLE_NAME, COLUMN_NAME'
        );

        $fixed = 0;

        foreach ($candidates as $candidate) {
            // The raw stored default only survives the round-trip for
            // genuinely suspect shapes; skip everything else cheaply.
            if (! $this->isSuspect($candidate->COLUMN_DEFAULT)) {
                continue;
            }

            $meta = DB::selectOne(
                'SHOW COLUMNS FROM `'.str_replace('`', '``', $candidate->TABLE_NAME)
                .'` WHERE Field = '.$this->quote($candidate->COLUMN_NAME)
            );

            if ($meta === null) {
                continue;
            }

            $decision = $this->classifyStoredDefault($meta->Default ?? null, ($meta->Null ?? 'NO') === 'YES');

            if ($decision === null) {
                continue;
            }

            $tableIdent = '`'.str_replace('`', '``', $candidate->TABLE_NAME).'`';
            $columnIdent = '`'.str_replace('`', '``', $candidate->COLUMN_NAME).'`';

            if ($decision['action'] === 'warn') {
                $this->warn("{$candidate->TABLE_NAME}.{$candidate->COLUMN_NAME}: stored default {$decision['label']} is corrupt but the column is NOT NULL — resolve manually.");

                continue;
            }

            $nullClause = ($meta->Null ?? 'NO') === 'YES' ? ' NULL' : ' NOT NULL';
            $defaultClause = $decision['action'] === 'null'
                ? ' DEFAULT NULL'
                : ' DEFAULT '.$this->quote((string) $decision['value']);

            DB::statement("ALTER TABLE {$tableIdent} MODIFY {$columnIdent} {$meta->Type}{$nullClause}{$defaultClause}");

            $this->line("{$candidate->TABLE_NAME}.{$candidate->COLUMN_NAME}: default {$decision['label']} repaired.");
            $fixed++;
        }

        $this->info("Column default normalization complete ({$fixed} default(s) repaired).");

        return self::SUCCESS;
    }

    /**
     * Cheap pre-filter on the information_schema view of the default:
     * MariaDB doubles a leading stored quote ('''x'''), and the NULL-text
     * defect shows as NULL or 'NULL'. Anything else cannot be corrupt.
     */
    protected function isSuspect(string $raw): bool
    {
        return str_starts_with($raw, "''")
            || strtoupper($raw) === 'NULL'
            || strtoupper($raw) === "'NULL'";
    }

    /**
     * Decide whether a SHOW COLUMNS raw default is corrupt and how to
     * repair it. Returns null when healthy; otherwise:
     *  - action 'set'  → replace with value (quote-embedded default)
     *  - action 'null' → drop the default to NULL (literal 'NULL' text)
     *  - action 'warn' → corrupt but unfixable automatically (NOT NULL)
     *
     * @return array{action: string, value: ?string, label: string}|null
     */
    protected function classifyStoredDefault(?string $stored, bool $nullable): ?array
    {
        if ($stored === null) {
            return null;
        }

        if (strlen($stored) >= 2 && str_starts_with($stored, "'") && str_ends_with($stored, "'")) {
            $unwrapped = str_replace("''", "'", substr($stored, 1, -1));

            if (strtoupper($unwrapped) === 'NULL') {
                return $nullable
                    ? ['action' => 'null', 'value' => null, 'label' => var_export($stored, true)]
                    : ['action' => 'warn', 'value' => null, 'label' => var_export($stored, true)];
            }

            return ['action' => 'set', 'value' => $unwrapped, 'label' => var_export($stored, true)];
        }

        if (strtoupper($stored) === 'NULL') {
            return $nullable
                ? ['action' => 'null', 'value' => null, 'label' => "'NULL'"]
                : ['action' => 'warn', 'value' => null, 'label' => "'NULL'"];
        }

        return null;
    }

    /**
     * @param  array<int, string>  $values
     */
    protected function quoteValues(array $values): string
    {
        return implode(',', array_map(fn (string $v) => $this->quote($v), $values));
    }

    protected function quote(string $value): string
    {
        return "'".str_replace("'", "''", $value)."'";
    }
}
