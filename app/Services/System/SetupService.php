<?php

namespace App\Services\System;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

class SetupService
{
    public function seedCoreData(array $config): void
    {
        $admin = User::create([
            'username' => $config['admin_username'] ?? 'admin',
            'email' => $config['admin_email'],
            'role' => 'admin',
            'mfa_enabled' => false,
            'is_active' => true,
        ]);

        $admin->password_hash = Hash::make($config['admin_password']);
        $admin->save();

        Artisan::call('db:seed', [
            '--class' => 'CurrencySeeder',
            '--force' => true,
        ]);

        Artisan::call('db:seed', [
            '--class' => 'ChartOfAccountsSeeder',
            '--force' => true,
        ]);

        Branch::create([
            'code' => 'HQ',
            'name' => $config['business_name'].' - Head Office',
            'type' => 'head_office',
            'is_active' => true,
            'is_main' => true,
        ]);
    }

    public function seedOptionalData(array $config): void
    {
        if ($config['setup_exchange_rates'] ?? false) {
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
}
