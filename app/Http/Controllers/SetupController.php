<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class SetupController extends Controller
{
    public function index()
    {
        $isSetupComplete = $this->isSetupComplete();

        return view('setup.index', [
            'isSetupComplete' => $isSetupComplete,
            'currentStep' => $this->getCurrentStep(),
        ]);
    }

    public function wizard(Request $request)
    {
        $step = $request->get('step', 1);

        return view('setup.wizard', [
            'step' => (int) $step,
            'currencies' => Currency::all(),
            'progress' => $this->calculateProgress(),
        ]);
    }

    public function quickSetup(Request $request)
    {
        $validated = $request->validate([
            'business_name' => 'required|string|max:255',
            'admin_email' => 'required|email',
            'admin_password' => 'required|min:8',
            'base_currency' => 'required|string|size:3',
            'setup_exchange_rates' => 'boolean',
            'setup_branch_pools' => 'boolean',
        ]);

        try {
            DB::beginTransaction();

            $this->runMigrations();
            $this->seedCoreData($validated);
            $this->seedOptionalData($validated);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Setup completed successfully!',
                'redirect' => '/login',
                'credentials' => [
                    'email' => $validated['admin_email'],
                    'password' => 'Use the password you provided',
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Setup failed: '.$e->getMessage(),
            ], 500);
        }
    }

    public function step1CompanyInfo(Request $request)
    {
        $validated = $request->validate([
            'business_name' => 'required|string|max:255',
            'business_address' => 'nullable|string',
            'business_phone' => 'nullable|string',
            'business_email' => 'nullable|email',
        ]);

        session(['setup.business' => $validated]);

        return redirect()->route('setup.wizard', ['step' => 2]);
    }

    public function step2AdminUser(Request $request)
    {
        $validated = $request->validate([
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|unique:users,email',
            'admin_password' => 'required|min:8|confirmed',
        ]);

        session(['setup.admin' => $validated]);

        return redirect()->route('setup.wizard', ['step' => 3]);
    }

    public function step3Currencies(Request $request)
    {
        $validated = $request->validate([
            'base_currency' => 'required|string|size:3',
            'active_currencies' => 'required|array|min:1',
            'active_currencies.*' => 'string|size:3',
        ]);

        session(['setup.currencies' => $validated]);

        return redirect()->route('setup.wizard', ['step' => 4]);
    }

    public function step4ExchangeRates(Request $request)
    {
        $validated = $request->validate([
            'use_default_rates' => 'boolean',
            'custom_rates' => 'nullable|array',
            'custom_rates.*.buy' => 'nullable|numeric|min:0.0001',
            'custom_rates.*.sell' => 'nullable|numeric|min:0.0001',
        ]);

        session(['setup.rates' => $validated]);

        return redirect()->route('setup.wizard', ['step' => 5]);
    }

    public function step5InitialStock(Request $request)
    {
        $validated = $request->validate([
            'initial_myr_cash' => 'required|numeric|min:0',
            'initial_stock' => 'nullable|array',
            'initial_stock.*' => 'nullable|numeric|min:0',
            'initial_foreign_cash' => 'nullable|array',
            'initial_foreign_cash.*' => 'nullable|numeric|min:0',
        ]);

        // Merge initial_stock and initial_foreign_cash into a single stock array
        $stock = $validated['initial_stock'] ?? [];
        if (isset($validated['initial_foreign_cash'])) {
            $stock = array_merge($stock, $validated['initial_foreign_cash']);
        }

        // Add MYR cash as part of initial stock
        $stock['MYR'] = $validated['initial_myr_cash'];

        session(['setup.stock' => ['initial_stock' => $stock, 'initial_myr_cash' => $validated['initial_myr_cash']]]);

        return redirect()->route('setup.wizard', ['step' => 6]);
    }

    public function completeSetup(Request $request)
    {
        $setupData = session('setup', []);

        try {
            DB::beginTransaction();

            $this->executeSetup($setupData);

            DB::commit();

            session()->forget('setup');

            return response()->json([
                'success' => true,
                'message' => 'Business setup completed successfully!',
                'redirect' => route('login'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function checkStatus()
    {
        return response()->json([
            'is_complete' => $this->isSetupComplete(),
            'current_step' => $this->getCurrentStep(),
            'progress' => $this->calculateProgress(),
            'missing_components' => $this->getMissingComponents(),
        ]);
    }

    public function resetSetup()
    {
        if (app()->environment('production')) {
            return response()->json([
                'success' => false,
                'message' => 'Reset not allowed in production',
            ], 403);
        }

        try {
            Artisan::call('migrate:fresh', ['--force' => true]);
            session()->forget('setup');

            return response()->json([
                'success' => true,
                'message' => 'Setup reset. You can start fresh.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function isSetupComplete(): bool
    {
        return User::exists() &&
               Currency::exists() &&
               ExchangeRate::exists() &&
               Branch::exists();
    }

    private function getCurrentStep(): int
    {
        if (! User::exists()) {
            return 1;
        }
        if (! Currency::exists()) {
            return 2;
        }
        if (! ExchangeRate::exists()) {
            return 3;
        }
        if (! Branch::exists()) {
            return 4;
        }

        return 5;
    }

    private function calculateProgress(): int
    {
        $checks = [
            User::exists(),
            Currency::exists(),
            ExchangeRate::exists(),
            Branch::exists(),
            ChartOfAccount::exists(),
        ];

        $completed = count(array_filter($checks));

        return (int) (($completed / count($checks)) * 100);
    }

    private function getMissingComponents(): array
    {
        $missing = [];

        if (! User::exists()) {
            $missing[] = 'admin_user';
        }
        if (! Currency::exists()) {
            $missing[] = 'currencies';
        }
        if (! ExchangeRate::exists()) {
            $missing[] = 'exchange_rates';
        }
        if (! Branch::exists()) {
            $missing[] = 'branches';
        }
        if (! ChartOfAccount::exists()) {
            $missing[] = 'chart_of_accounts';
        }

        return $missing;
    }

    private function runMigrations(): void
    {
        Artisan::call('migrate', ['--force' => true]);
    }

    private function seedCoreData(array $config): void
    {
        $admin = User::create([
            'username' => 'admin',
            'email' => $config['admin_email'],
            'password' => $config['admin_password'],
            'role' => 'admin',
            'mfa_enabled' => false,
            'is_active' => true,
        ]);

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

    private function seedOptionalData(array $config): void
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

    private function executeSetup(array $setupData): void
    {
        $this->runMigrations();

        if (isset($setupData['admin'])) {
            User::create([
                'username' => $setupData['admin']['admin_name'],
                'email' => $setupData['admin']['admin_email'],
                'password' => $setupData['admin']['admin_password'],
                'role' => 'admin',
                'mfa_enabled' => false,
                'is_active' => true,
            ]);
        }

        Artisan::call('db:seed', ['--class' => 'CurrencySeeder', '--force' => true]);
        Artisan::call('db:seed', ['--class' => 'ChartOfAccountsSeeder', '--force' => true]);
        Artisan::call('db:seed', ['--class' => 'FiscalYearSeeder', '--force' => true]);
        Artisan::call('db:seed', ['--class' => 'AccountingPeriodSeeder', '--force' => true]);

        if (isset($setupData['business'])) {
            Branch::create([
                'code' => 'HQ',
                'name' => $setupData['business']['business_name'],
                'address' => $setupData['business']['business_address'] ?? null,
                'phone' => $setupData['business']['business_phone'] ?? null,
                'email' => $setupData['business']['business_email'] ?? null,
                'type' => 'head_office',
                'is_active' => true,
                'is_main' => true,
            ]);
        }

        if (isset($setupData['currencies'])) {
            // Base currency is set via session/config - no is_base column needed
            // The base currency code is stored in $setupData['currencies']['base_currency']
        }

        if (isset($setupData['rates']) && ($setupData['rates']['use_default_rates'] ?? false)) {
            Artisan::call('db:seed', ['--class' => 'ExchangeRateSeeder', '--force' => true]);
        }

        if (isset($setupData['stock'])) {
            $this->createInitialStock($setupData['stock']);
        }
    }

    private function createInitialStock(array $stockData): void
    {
        $branch = Branch::where('code', 'HQ')->first();

        if ($branch && isset($stockData['initial_stock'])) {
            foreach ($stockData['initial_stock'] as $currencyCode => $amount) {
                if ($amount > 0) {
                    DB::table('branch_pools')->insert([
                        'branch_id' => $branch->id,
                        'currency_code' => $currencyCode,
                        'available_balance' => (string) $amount,
                        'allocated_balance' => '0.00',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}
