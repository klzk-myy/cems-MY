<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\SetupRequest;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\User;
use App\Services\System\MathService;
use App\Services\System\SetupService;
use Database\Seeders\SchemaSeeder;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class SetupController extends Controller
{
    public function __construct(
        protected SetupService $setupService,
        protected MathService $mathService,
    ) {}

    public function index(Request $request): View
    {
        $isSetupComplete = $this->isSetupComplete();

        if ($isSetupComplete) {
            return view('setup.index', [
                'isSetupComplete' => true,
                'currentStep' => 7,
                'progress' => 100,
            ]);
        }

        $step = $request->get('step', $this->getCurrentStep());

        $currencies = Currency::select('code', 'name', 'symbol')->where('is_active', true)->get();

        // Steps 5/6 must iterate the step-3 selection, not the DB list — that
        // is where unchecked currencies get dropped and a custom "other"
        // currency (not yet in the table) picks up its stock/balance inputs.
        $selected = collect((array) session('setup.currencies.active_currencies', []))
            ->map(fn ($c) => strtoupper((string) $c))
            ->unique()
            ->values();

        $setupCurrencies = $selected->isEmpty()
            ? $currencies
            : $currencies->whereIn('code', $selected)->values()->toBase()
                ->merge(
                    $selected->diff($currencies->pluck('code'))->map(fn ($code) => (object) [
                        'code' => $code,
                        'name' => session('setup.currencies.custom_currency_name') ?: $code,
                        'symbol' => session('setup.currencies.custom_currency_symbol') ?: $code,
                    ])
                );

        return view('setup.index', [
            'isSetupComplete' => false,
            'currentStep' => (int) $step,
            'progress' => $this->calculateProgress(),
            'currencies' => $currencies,
            'setupCurrencies' => $setupCurrencies,
        ]);
    }

    public function wizard(Request $request): RedirectResponse
    {
        $step = $request->get('step', $this->getCurrentStep());

        return redirect()->route('setup.index', ['step' => $step]);
    }

    public function quickSetup(SetupRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $this->runMigrations();
            $this->seedCoreData($validated);
            $this->seedOptionalData($validated);

            // Persist the immutable completion marker inside the same
            // transaction so an aborted setup cannot leave the wizard open.
            $this->setupService->markSetupComplete();

            DB::commit();

            $this->flashSanctionsBootstrapNotice();

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
            Log::error('Setup wizard quickSetup failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Setup failed. Please check the server logs or try again.',
            ], 500);
        }
    }

    public function step1CompanyInfo(SetupRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        session(['setup.business' => $validated]);

        return redirect()->route('setup.wizard', ['step' => 2]);
    }

    public function step2AdminUser(SetupRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        session(['setup.admin' => $validated]);

        return redirect()->route('setup.wizard', ['step' => 3]);
    }

    public function step3Currencies(SetupRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // A custom currency is implicitly selected: fold its code into the
        // active set so the review screen, stock/balance steps, and
        // executeSetup all see it without special-casing.
        $customCode = strtoupper(trim((string) ($validated['custom_currency_code'] ?? '')));

        if ($customCode !== '') {
            $validated['custom_currency_code'] = $customCode;
            $validated['active_currencies'] = array_values(array_unique(
                array_map('strtoupper', [...$validated['active_currencies'], $customCode])
            ));
        }

        session(['setup.currencies' => $validated]);

        return redirect()->route('setup.wizard', ['step' => 4]);
    }

    public function step4ExchangeRates(SetupRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        session(['setup.rates' => $validated]);

        return redirect()->route('setup.wizard', ['step' => 5]);
    }

    public function step5InitialStock(SetupRequest $request): RedirectResponse
    {
        $validated = $request->validated();

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

    public function step6OpeningBalance(SetupRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        session(['setup.opening_balance' => $validated]);

        return redirect()->route('setup.wizard', ['step' => 7]);
    }

    public function completeSetup(Request $request): JsonResponse
    {
        $setupData = session('setup', []);

        try {
            DB::beginTransaction();

            $this->executeSetup($setupData);

            // Persist the immutable completion marker inside the same
            // transaction so an aborted setup cannot leave the wizard open.
            $this->setupService->markSetupComplete();

            DB::commit();

            session()->forget('setup');

            $this->flashSanctionsBootstrapNotice();

            return response()->json([
                'success' => true,
                'message' => 'Business setup completed successfully!',
                'redirect' => route('login'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Setup wizard completeSetup failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Setup could not be completed. Please check the server logs or try again.',
            ], 500);
        }
    }

    public function checkStatus(): JsonResponse
    {
        return response()->json([
            'is_complete' => $this->isSetupComplete(),
            'current_step' => $this->getCurrentStep(),
            'progress' => $this->calculateProgress(),
            'missing_components' => $this->getMissingComponents(),
        ]);
    }

    public function resetSetup(Application $app): JsonResponse
    {
        if ($app->environment('production')) {
            return response()->json([
                'success' => false,
                'message' => 'Reset not allowed in production',
            ], 403);
        }

        try {
            Artisan::call('db:seed', ['--class' => SchemaSeeder::class, '--force' => true]);
            session()->forget('setup');

            // SchemaSeeder drops setup_state; clear defensively in
            // case the drop failed so the wizard can never stay locked out.
            $this->setupService->clearCompleted();

            return response()->json([
                'success' => true,
                'message' => 'Setup reset. You can start fresh.',
            ]);
        } catch (\Exception $e) {
            Log::error('Setup wizard resetSetup failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Setup could not be reset. Please check the server logs or try again.',
            ], 500);
        }
    }

    private function getSetupChecks(): array
    {
        return [
            'admin_user' => User::exists(),
            'currencies' => Currency::exists(),
            'exchange_rates' => ExchangeRate::exists(),
            'branches' => Branch::exists(),
            'chart_of_accounts' => ChartOfAccount::exists(),
        ];
    }

    private function isSetupComplete(): bool
    {
        return collect($this->getSetupChecks())
            ->only(['admin_user', 'currencies', 'exchange_rates', 'branches'])
            ->every(fn ($check) => $check);
    }

    private function getCurrentStep(): int
    {
        $checks = $this->getSetupChecks();

        if (! $checks['admin_user']) {
            return 1;
        }
        if (! $checks['currencies']) {
            return 2;
        }
        if (! $checks['exchange_rates']) {
            return 3;
        }
        if (! $checks['branches']) {
            return 4;
        }

        return 5;
    }

    private function calculateProgress(): int
    {
        $checks = $this->getSetupChecks();
        $completed = count(array_filter($checks));

        return (int) (($completed / count($checks)) * 100);
    }

    private function getMissingComponents(): array
    {
        return array_keys(array_filter(
            $this->getSetupChecks(),
            fn ($check) => ! $check
        ));
    }

    private function runMigrations(): void
    {
        // Build the schema only when it is missing. SchemaSeeder is
        // destructive (drops every table), so on an already-populated
        // database it must never re-run here; an existing schema is treated
        // as current.
        if (Schema::hasTable('users')) {
            return;
        }

        Artisan::call('db:seed', ['--class' => SchemaSeeder::class, '--force' => true]);
    }

    private function seedCoreData(array $config): void
    {
        $this->setupService->seedCoreData($config);
    }

    private function seedOptionalData(array $config): void
    {
        $this->setupService->seedOptionalData($config);
    }

    /**
     * Fresh installs have empty sanctions lists until the first scheduled
     * import (daily 01:00). Tell operators to load them immediately so the
     * gap is closed deliberately, not silently.
     */
    private function flashSanctionsBootstrapNotice(): void
    {
        session()->flash('info',
            'Sanctions lists are not loaded yet. Run "php artisan sanctions:update" now - '
            .'sanctions screening is ineffective until the lists are imported.'
        );
    }

    private function executeSetup(array $setupData): void
    {
        $this->runMigrations();

        $hqBranch = null;
        if (isset($setupData['business'])) {
            $hqBranch = Branch::create([
                'code' => 'HQ',
                'name' => $setupData['business']['business_name'],
                'address' => $setupData['business']['business_address'] ?? null,
                'phone' => $setupData['business']['business_phone'] ?? null,
                'email' => $setupData['business']['business_email'] ?? null,
                'type' => 'head_office',
                'is_active' => true,
                'is_main' => true,
            ]);

            // A usable business needs at least one till; there is no counter
            // CRUD UI, so create a default counter bound to HQ.
            Counter::firstOrCreate(
                ['code' => 'C01'],
                ['name' => 'Counter 1', 'status' => 'active', 'branch_id' => $hqBranch->id],
            );
        }

        if (isset($setupData['admin'])) {
            // Pass the plain password - the mutator hashes it once. Hashing
            // here as well would double-hash and lock the admin out.
            $user = User::create([
                'username' => $setupData['admin']['admin_name'],
                'email' => $setupData['admin']['admin_email'],
                'password' => $setupData['admin']['admin_password'],
                'branch_id' => $hqBranch?->id,
                'mfa_enabled' => false,
                'is_active' => true,
            ]);

            $user->role = UserRole::Admin;
            $user->save();
        }

        Artisan::call('db:seed', ['--class' => 'CurrencySeeder', '--force' => true]);
        Artisan::call('db:seed', ['--class' => 'EnhancedChartOfAccountsSeeder', '--force' => true]);

        // A custom "other" currency entered in step 3 may not exist in the
        // seeded list — create it before applying the active set.
        $customCode = strtoupper(trim((string) ($setupData['currencies']['custom_currency_code'] ?? '')));
        if ($customCode !== '') {
            Currency::firstOrCreate(
                ['code' => $customCode],
                [
                    'name' => $setupData['currencies']['custom_currency_name'] ?? $customCode,
                    'symbol' => $setupData['currencies']['custom_currency_symbol'] ?? $customCode,
                    'decimal_places' => 2,
                    'is_active' => true,
                ],
            );
        }

        // Honor the step-3 selection: deactivate currencies the business did
        // not enable. MYR is the system base and must always stay active.
        if (isset($setupData['currencies']['active_currencies'])) {
            $active = $setupData['currencies']['active_currencies'];
            $active[] = 'MYR';
            Currency::whereNotIn('code', $active)->update(['is_active' => false]);
            Currency::whereIn('code', $active)->update(['is_active' => true]);
        }

        // Shared with quickSetup so both install paths guarantee the same
        // fiscal-year / accounting-period preconditions.
        $this->setupService->ensureFiscalYearAndPeriods();

        if (isset($setupData['rates']) && ($setupData['rates']['use_default_rates'] ?? false)) {
            Artisan::call('db:seed', ['--class' => 'ExchangeRateSeeder', '--force' => true]);
        }

        // Step-4 custom rates (e.g. for a step-3 "other" currency the seeder
        // does not cover) become real exchange_rates rows.
        foreach ($setupData['rates']['custom_rates'] ?? [] as $code => $rate) {
            $code = strtoupper(trim((string) $code));
            if ($code === '' || ! isset($rate['buy'], $rate['sell'])) {
                continue;
            }

            ExchangeRate::updateOrCreate(
                ['currency_code' => $code],
                [
                    'rate_buy' => $rate['buy'],
                    'rate_sell' => $rate['sell'],
                    'source' => 'setup_custom',
                    'fetched_at' => now(),
                ],
            );
        }

        if (isset($setupData['stock'])) {
            $this->createInitialStock($setupData['stock']);
        }

        if (isset($setupData['opening_balance'])) {
            $this->createOpeningBalance($setupData['opening_balance']);
        }
    }

    private function createOpeningBalance(array $balanceData): void
    {
        $this->setupService->createOpeningBalance($balanceData);
    }

    private function createInitialStock(array $stockData): void
    {
        $this->setupService->createInitialStock($stockData);
    }
}
