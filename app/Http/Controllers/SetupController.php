<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\SetupRequest;
use App\Models\Branch;
use App\Models\ChartOfAccount;
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

        return view('setup.index', [
            'isSetupComplete' => false,
            'currentStep' => (int) $step,
            'progress' => $this->calculateProgress(),
            'currencies' => Currency::select('code', 'name', 'symbol')->where('is_active', true)->get(),
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

        if (isset($setupData['admin'])) {
            // Pass the plain password - the mutator hashes it once. Hashing
            // here as well would double-hash and lock the admin out.
            $user = User::create([
                'username' => $setupData['admin']['admin_name'],
                'email' => $setupData['admin']['admin_email'],
                'password' => $setupData['admin']['admin_password'],
                'mfa_enabled' => false,
                'is_active' => true,
            ]);

            $user->role = UserRole::Admin;
            $user->save();
        }

        Artisan::call('db:seed', ['--class' => 'CurrencySeeder', '--force' => true]);
        Artisan::call('db:seed', ['--class' => 'EnhancedChartOfAccountsSeeder', '--force' => true]);

        // Shared with quickSetup so both install paths guarantee the same
        // fiscal-year / accounting-period preconditions.
        $this->setupService->ensureFiscalYearAndPeriods();

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

        if (isset($setupData['rates']) && ($setupData['rates']['use_default_rates'] ?? false)) {
            Artisan::call('db:seed', ['--class' => 'ExchangeRateSeeder', '--force' => true]);
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
