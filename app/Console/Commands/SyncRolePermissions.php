<?php

namespace App\Console\Commands;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\RolePermission;
use App\Services\System\PermissionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bring an existing database's role_permissions storage up to date with the
 * Permission enum. SchemaSeeder only runs on fresh installs, so databases
 * seeded before a permission was added have two gaps:
 *
 * 1. The `permission` ENUM column cannot store the new value — any matrix
 *    write touching it fails with SQLSTATE 01000/1265.
 * 2. Rows seeded for the new permission (or stale granted=0 seeds) never
 *    reflect the updated built-in defaults.
 *
 * This command widens the enum to Permission::cases() and rewrites only rows
 * that carry no operator decision (missing rows and rows with updated_by
 * NULL, which are the ones seedDefaults wrote). Rows an admin explicitly
 * toggled keep their stored value. Idempotent; safe to re-run.
 */
class SyncRolePermissions extends Command
{
    protected $signature = 'permissions:sync';

    protected $description = 'Widen the role_permissions enum to the Permission enum and apply defaults to un-customized rows';

    public function handle(PermissionService $permissions): int
    {
        if (! Schema::hasTable('role_permissions')) {
            $this->warn('role_permissions table does not exist — nothing to sync.');

            return self::SUCCESS;
        }

        $this->widenEnum();

        $defaults = Permission::defaultMatrix();
        $synced = 0;

        foreach (UserRole::cases() as $role) {
            foreach (Permission::cases() as $permission) {
                $granted = in_array($permission->value, $defaults[$role->value] ?? [], true);

                $row = RolePermission::query()
                    ->where('role', $role->value)
                    ->where('permission', $permission->value)
                    ->first();

                if ($row === null) {
                    RolePermission::create([
                        'role' => $role->value,
                        'permission' => $permission->value,
                        'granted' => $granted,
                    ]);
                    $synced++;
                } elseif ($row->updated_by === null && (bool) $row->granted !== $granted) {
                    $row->update(['granted' => $granted]);
                    $synced++;
                }
            }
        }

        $permissions->clearCache();

        $this->info("role_permissions synced ({$synced} rows created/updated; admin overrides preserved).");

        return self::SUCCESS;
    }

    private function widenEnum(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return; // SQLite and friends have no native ENUM to widen.
        }

        $values = array_column(Permission::cases(), 'value');
        $quoted = implode(',', array_map(
            fn (string $v) => "'".str_replace("'", "''", $v)."'",
            $values
        ));

        $column = DB::selectOne("SHOW COLUMNS FROM role_permissions WHERE Field = 'permission'");
        $current = $column->Type ?? '';

        if ($current === 'enum('.$quoted.')') {
            $this->info('role_permissions.permission enum already current.');

            return;
        }

        DB::statement("ALTER TABLE role_permissions MODIFY permission ENUM({$quoted}) NOT NULL");

        $this->info('role_permissions.permission enum widened to '.count($values).' values.');
    }
}
