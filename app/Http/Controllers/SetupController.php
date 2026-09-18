<?php

namespace App\Http\Controllers;

use App\Exceptions\Domain\DomainException;
use App\Http\Requests\SetupRequest;
use App\Models\Currency;
use App\Services\System\SetupService;
use App\Services\Transaction\RateApiService;
use Database\Seeders\SchemaSeeder;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SetupController extends Controller
{
    public function __construct(
        protected SetupService $setupService,
        protected RateApiService $rateApiService,
    ) {}

    public function index(Request $request): View
    {
        $isSetupComplete = $this->setupService->isDataComplete();

        if ($isSetupComplete) {
            return view('setup.index', [
                'isSetupComplete' => true,
                'currentStep' => 7,
                'progress' => 100,
            ]);
        }

        $step = $request->get('step', $this->setupService->currentStep());

        $currencies = Currency::select('code', 'name', 'symbol')->where('is_active', true)->get();

        // Steps 5/6 must iterate the step-3 selection, not the DB list — that
        // is where unchecked currencies get dropped and a custom "other"
        // currency (not yet in the table) picks up its stock/balance inputs.
        $selected = collect((array) session('setup.currencies.active_currencies', []))
            ->map(fn ($c) => strtoupper((string) $c))
            ->unique()
            ->values();

        $customByCode = collect($this->setupService->customCurrencyRows(
            (array) session('setup.currencies', [])
        ))->keyBy('code');

        $setupCurrencies = $selected->isEmpty()
            ? $currencies
            : $currencies->whereIn('code', $selected)->values()->toBase()
                ->merge(
                    $selected->diff($currencies->pluck('code'))->map(fn ($code) => (object) [
                        'code' => $code,
                        'name' => $customByCode->get($code)['name'] ?? $code,
                        'symbol' => $customByCode->get($code)['symbol'] ?? $code,
                    ])
                );

        $unseededCustomCodes = $customByCode->keys()
            ->diff($currencies->pluck('code'))
            ->values();

        return view('setup.index', [
            'isSetupComplete' => false,
            'currentStep' => (int) $step,
            'progress' => $this->setupService->progress(),
            'currencies' => $currencies,
            'setupCurrencies' => $setupCurrencies,
            'unseededCustomCodes' => $unseededCustomCodes,
        ]);
    }

    public function wizard(Request $request): RedirectResponse
    {
        $step = $request->get('step', $this->setupService->currentStep());

        return redirect()->route('setup.index', ['step' => $step]);
    }

    public function quickSetup(SetupRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $this->setupService->ensureSchemaExists();
            $this->setupService->seedCoreData($validated);
            $this->setupService->seedOptionalData($validated);

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
        } catch (ValidationException|DomainException $e) {
            throw $e;
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

        // Custom currency rows are implicitly selected: fold their codes into
        // the active set so the review screen, stock/balance steps, and
        // executeSetup all see them without special-casing.
        $customRows = $this->setupService->customCurrencyRows($validated);
        $validated['custom_currencies'] = $customRows;

        if ($customRows !== []) {
            $validated['active_currencies'] = array_values(array_unique(
                array_map('strtoupper', [
                    ...$validated['active_currencies'],
                    ...array_column($customRows, 'code'),
                ])
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
        $stock[Currency::baseCurrency()] = $validated['initial_myr_cash'];

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

        // A bare POST (expired or never-started session) must not complete:
        // executeSetup would still seed reference data and set the immutable
        // setup_state marker, after which EnsureSetupAccessible locks every
        // /setup route — while setup.reset requires an admin login that was
        // never created. That combination bricks the install. 'business' and
        // 'admin' are the load-bearing steps; 'currencies' may be absent and
        // the seeders still produce a usable install.
        foreach (['business', 'admin'] as $requiredKey) {
            if (empty($setupData[$requiredKey])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Setup session is incomplete. Please restart the wizard.',
                ], 422);
            }
        }

        try {
            DB::beginTransaction();

            $this->setupService->executeSetup($setupData);

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
        } catch (ValidationException|DomainException $e) {
            throw $e;
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
            'is_complete' => $this->setupService->isDataComplete(),
            'current_step' => $this->setupService->currentStep(),
            'progress' => $this->setupService->progress(),
            'missing_components' => $this->setupService->missingComponents(),
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
        } catch (ValidationException|DomainException $e) {
            throw $e;
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

    /**
     * Fetch live market rates for the step-4 "other currency" fields.
     * Read-only: the wizard only prefills the form — the submitted values
     * are written by executeSetup like any manually entered rate.
     */
    public function fetchRates(Request $request): JsonResponse
    {
        $codes = array_values(array_unique(array_filter(array_map(
            fn ($code) => strtoupper(trim((string) $code)),
            (array) $request->input('codes', []),
        ))));

        if ($codes === []) {
            return response()->json([
                'success' => false,
                'message' => 'No currency codes supplied.',
            ], 422);
        }

        try {
            $rates = $this->rateApiService->previewRates($codes);
        } catch (\Throwable) {
            return response()->json([
                'success' => false,
                'message' => 'Rates could not be fetched. Enter them manually.',
            ], 502);
        }

        return response()->json([
            'success' => true,
            'rates' => $rates,
            'missing' => array_values(array_diff($codes, array_keys($rates))),
        ]);
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
}
