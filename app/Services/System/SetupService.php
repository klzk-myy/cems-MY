<?php

namespace App\Services\System;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\PasswordHistory;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SetupService
{
    /**
     * Persist the immutable setup-completed marker.
     *
     * Must be invoked inside the setup transaction so that a failure to record
     * the marker rolls back the entire setup (fail-closed).
     */
    public function markSetupComplete(): void
    {
        DB::table('setup_state')->updateOrInsert(
            ['id' => 1],
            [
                'setup_completed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Whether the immutable setup-completed marker is present.
     *
     * Returns false when the table does not exist yet (pre-migration installs)
     * so the data-derived fallback in EnsureSetupAccessible still applies.
     */
    public function isCompleted(): bool
    {
        try {
            return DB::table('setup_state')
                ->whereNotNull('setup_completed_at')
                ->exists();
        } catch (QueryException $e) {
            return false;
        }
    }

    /**
     * Clear the marker. Only reachable through the admin-gated,
     * non-production reset endpoint; migrate:fresh already drops the table,
     * this is belt-and-braces for partial failures.
     */
    public function clearCompleted(): void
    {
        try {
            DB::table('setup_state')->delete();
        } catch (QueryException $e) {
            // Table absent - nothing to clear.
        }
    }

    public function seedCoreData(array $config): void
    {
        $this->validateAdminPassword($config['admin_password'] ?? '');

        // Route through the password mutator: User::creating rejects any
        // creation attempt whose password_hash is still empty.
        $admin = new User([
            'username' => $config['admin_username'] ?? 'admin',
            'email' => $config['admin_email'],
            'password' => $config['admin_password'],
            'mfa_enabled' => false,
            'is_active' => true,
        ]);

        $admin->role = UserRole::Admin;
        $admin->save();

        // Seed history with the initial hash so reuse prevention covers it.
        PasswordHistory::record($admin->id, $admin->password_hash);

        Artisan::call('db:seed', [
            '--class' => 'CurrencySeeder',
            '--force' => true,
        ]);

        Artisan::call('db:seed', [
            '--class' => 'EnhancedChartOfAccountsSeeder',
            '--force' => true,
        ]);

        Branch::create([
            'code' => 'HQ',
            'name' => $config['business_name'].' - Head Office',
            'type' => 'head_office',
            'is_active' => true,
            'is_main' => true,
        ]);

        $this->ensureFiscalYearAndPeriods();
    }

    /**
     * Ensure the current fiscal year and monthly accounting periods exist
     * and are open. Shared by both setup paths (quick setup and step
     * wizard) so accounting preconditions can never diverge between them.
     */
    public function ensureFiscalYearAndPeriods(): void
    {
        Artisan::call('db:seed', [
            '--class' => 'FiscalYearSeeder',
            '--force' => true,
        ]);

        Artisan::call('db:seed', [
            '--class' => 'AccountingPeriodSeeder',
            '--force' => true,
        ]);
    }

    public function seedOptionalData(array $config): void
    {
        // Default-enabled unless explicitly opted out so fresh installs get
        // usable reference rates without an extra decision during setup.
        if ($config['setup_exchange_rates'] ?? true) {
            Artisan::call('db:seed', [
                '--class' => 'ExchangeRateSeeder',
                '--force' => true,
            ]);
        }

        if ($config['setup_branch_pools'] ?? false) {
            Artisan::call('db:seed', [
                '--class' => 'BranchPoolSeeder',
                '--force' => true,
            ]);
        }
    }

    protected function validateAdminPassword(string $password): void
    {
        if (strlen($password) < 12) {
            throw new \InvalidArgumentException('Admin password must be at least 12 characters');
        }

        if (! preg_match('/[A-Z]/', $password)) {
            throw new \InvalidArgumentException('Admin password must contain at least one uppercase letter');
        }

        if (! preg_match('/[a-z]/', $password)) {
            throw new \InvalidArgumentException('Admin password must contain at least one lowercase letter');
        }

        if (! preg_match('/[0-9]/', $password)) {
            throw new \InvalidArgumentException('Admin password must contain at least one digit');
        }

        if (! preg_match('/[^A-Za-z0-9]/', $password)) {
            throw new \InvalidArgumentException('Admin password must contain at least one special character');
        }
    }
}
