# POS Module - Core Functionality Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build core POS functionality including daily rate management, transaction processing, and inventory tracking with EOD balancing.

**Architecture:** Modular POS module within existing Laravel app, leveraging existing services (TransactionService, ComplianceService, CounterService, CurrencyPositionService) with POS-specific UI and orchestration layer.

**Tech Stack:** Laravel 10.x, PHP 8.1+, MySQL, Redis, PHPUnit, BCMath

---

## Phase 1: Foundation - Daily Rate Management

### Task 1: Create customer_type migration
**Files:** database/migrations/

- [ ] **Step 1: Run migration generator**
```bash
php artisan make:migration add_customer_type_to_customers_table --table=customers
```

- [ ] **Step 2: Edit migration with enum fields**
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->enum('customer_type', ['individual', 'corporate'])->default('individual')->after('id');
            $table->index('customer_type');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['customer_type']);
            $table->dropColumn('customer_type');
        });
    }
};
```

- [ ] **Step 3: Run migration**
```bash
php artisan migrate
```

---

### Task 2: Create pos_daily_rates migration
**Files:** database/migrations/

- [ ] **Step 1: Run migration generator**
```bash
php artisan make:migration create_pos_daily_rates_table
```

- [ ] **Step 2: Edit migration with table schema**
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_daily_rates', function (Blueprint $table) {
            $table->id();
            $table->date('rate_date');
            $table->string('currency_code', 3);
            $table->decimal('buy_rate', 10, 6);
            $table->decimal('sell_rate', 10, 6);
            $table->decimal('mid_rate', 10, 6);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->unique(['rate_date', 'currency_code']);
            $table->index(['rate_date', 'currency_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_daily_rates');
    }
};
```

- [ ] **Step 3: Run migration**
```bash
php artisan migrate
```

---

### Task 3: Create PosDailyRate model
**Files:** app/Modules/Pos/Models/PosDailyRate.php

- [ ] **Step 1: Create directories**
```bash
mkdir -p app/Modules/Pos/Models app/Modules/Pos/Services app/Modules/Pos/Controllers app/Modules/Pos/Requests
mkdir -p resources/views/pos/rates resources/views/pos/transaction resources/views/pos/inventory
mkdir -p tests/Unit/Pos tests/Feature/Pos
```

- [ ] **Step 2: Create PosDailyRate model**
```php
<?php
namespace App\Modules\Pos\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosDailyRate extends Model
{
    use HasFactory;

    protected $table = 'pos_daily_rates';

    protected $fillable = [
        'rate_date', 'currency_code', 'buy_rate', 'sell_rate', 'mid_rate', 'is_active', 'created_by',
    ];

    protected $casts = [
        'rate_date' => 'date',
        'buy_rate' => 'decimal:6',
        'sell_rate' => 'decimal:6',
        'mid_rate' => 'decimal:6',
        'is_active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('rate_date', $date);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForCurrency($query, $currencyCode)
    {
        return $query->where('currency_code', $currencyCode);
    }
}
```

- [ ] **Step 3: Create factory**
```bash
php artisan make:factory PosDailyRateFactory --model=App\\Modules\\Pos\\Models\\PosDailyRate
```

- [ ] **Step 4: Edit factory definition**
```php
<?php
namespace Database\Factories;

use App\Modules\Pos\Models\PosDailyRate;
use Illuminate\Database\Eloquent\Factories\Factory;

class PosDailyRateFactory extends Factory
{
    protected $model = PosDailyRate::class;

    public function definition(): array
    {
        return [
            'rate_date' => $this->faker->date(),
            'currency_code' => $this->faker->randomElement(['USD', 'EUR', 'GBP', 'SGD', 'JPY']),
            'buy_rate' => $this->faker->randomFloat(6, 1, 10),
            'sell_rate' => $this->faker->randomFloat(6, 1, 10),
            'mid_rate' => $this->faker->randomFloat(6, 1, 10),
            'is_active' => true,
            'created_by' => \App\Models\User::factory(),
        ];
    }
}
```

---

### Task 4: Create PosRateService
**Files:** app/Modules/Pos/Services/PosRateService.php

- [ ] **Step 1: Create service**
```php
<?php
namespace App\Modules\Pos\Services;

use App\Modules\Pos\Models\PosDailyRate;
use App\Services\MathService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PosRateService
{
    protected MathService $mathService;
    protected int $cacheTtl;

    public function __construct(MathService $mathService)
    {
        $this->mathService = $mathService;
        $this->cacheTtl = config('pos.rate_cache_ttl', 3600);
    }

    public function getTodayRates(): ?array
    {
        $cacheKey = 'pos:rates:today';

        return Cache::remember($cacheKey, $this->cacheTtl, function () {
            $rates = PosDailyRate::forDate(today())->active()->get();

            if ($rates->isEmpty()) {
                return null;
            }

            return $rates->mapWithKeys(function ($rate) {
                return [
                    $rate->currency_code => [
                        'buy' => $this->mathService->bcadd($rate->buy_rate, '0'),
                        'sell' => $this->mathService->bcadd($rate->sell_rate, '0'),
                        'mid' => $this->mathService->bcadd($rate->mid_rate, '0'),
                    ]
                ];
            })->toArray();
        });
    }

    public function getRatesForDate(string $date): ?array
    {
        $rates = PosDailyRate::forDate($date)->active()->get();

        if ($rates->isEmpty()) {
            return null;
        }

        return $rates->mapWithKeys(function ($rate) {
            return [
                $rate->currency_code => [
                    'buy' => $this->mathService->bcadd($rate->buy_rate, '0'),
                    'sell' => $this->mathService->bcadd($rate->sell_rate, '0'),
                    'mid' => $this->mathService->bcadd($rate->mid_rate, '0'),
                ]
            ];
        })->toArray();
    }

    public function setDailyRates(array $rates, int $userId): bool
    {
        try {
            foreach ($rates as $currencyCode => $rateData) {
                PosDailyRate::updateOrCreate(
                    ['rate_date' => today()->toDateString(), 'currency_code' => $currencyCode],
                    [
                        'buy_rate' => $rateData['buy'],
                        'sell_rate' => $rateData['sell'],
                        'mid_rate' => $rateData['mid'],
                        'is_active' => true,
                        'created_by' => $userId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }

            Cache::forget('pos:rates:today');

            Log::info('POS daily rates set', ['user_id' => $userId, 'date' => today()->toDateString()]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to set POS daily rates', ['user_id' => $userId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public function copyPreviousDayRates(): ?array
    {
        $yesterday = Carbon::yesterday()->toDateString();
        return $this->getRatesForDate($yesterday);
    }

    public function getRateHistory(int $days = 7): array
    {
        $history = [];

        for ($i = 0; $i < $days; $i++) {
            $date = Carbon::today()->subDays($i)->toDateString();
            $rates = $this->getRatesForDate($date);

            if ($rates !== null) {
                $history[$date] = $rates;
            }
        }

        return $history;
    }

    public function getRateForCurrency(string $currencyCode, ?string $date = null): ?array
    {
        $date = $date ?? today()->toDateString();

        $rate = PosDailyRate::forDate($date)->forCurrency($currencyCode)->active()->first();

        if ($rate === null) {
            return null;
        }

        return [
            'buy' => $this->mathService->bcadd($rate->buy_rate, '0'),
            'sell' => $this->mathService->bcadd($rate->sell_rate, '0'),
            'mid' => $this->mathService->bcadd($rate->mid_rate, '0'),
        ];
    }

    public function invalidateCache(): void
    {
        Cache::forget('pos:rates:today');
    }
}
```

- [ ] **Step 2: Create config file** config/pos.php
```php
<?php
return [
    'rate_cache_ttl' => env('POS_RATE_CACHE_TTL', 3600),
    'receipt_storage_path' => env('POS_RECEIPT_STORAGE_PATH', storage_path('app/receipts')),
    'thermal_printer_default' => env('POS_THERMAL_PRINTER_DEFAULT', '58mm'),
    'eod_variance_yellow' => env('POS_EOD_VARIANCE_YELLOW', 100),
    'eod_variance_red' => env('POS_EOD_VARIANCE_RED', 500),
];
```

---

### Task 5: Create PosRateRequest and PosRateController
**Files:** app/Modules/Pos/Requests/PosRateRequest.php, app/Modules/Pos/Controllers/PosRateController.php

- [ ] **Step 1: Create PosRateRequest**
```php
<?php
namespace App\Modules\Pos\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PosRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rates' => 'required|array|min:1',
            'rates.*.buy' => 'required|numeric|min:0|max:999999.999999',
            'rates.*.sell' => 'required|numeric|min:0|max:999999.999999',
            'rates.*.mid' => 'required|numeric|min:0|max:999999.999999',
        ];
    }
}
```

- [ ] **Step 2: Create PosRateController**
```php
<?php
namespace App\Modules\Pos\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Pos\Models\PosDailyRate;
use App\Modules\Pos\Requests\PosRateRequest;
use App\Modules\Pos\Services\PosRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class PosRateController extends Controller
{
    protected PosRateService $rateService;

    public function __construct(PosRateService $rateService)
    {
        $this->rateService = $rateService;
    }

    public function index(): View
    {
        $todayRates = $this->rateService->getTodayRates();
        $rateHistory = $this->rateService->getRateHistory(7);

        return view('pos.rates.index', [
            'todayRates' => $todayRates,
            'rateHistory' => $rateHistory,
        ]);
    }

    public function getTodayRates(): JsonResponse
    {
        $rates = $this->rateService->getTodayRates();

        return response()->json([
            'date' => today()->toDateString(),
            'rates' => $rates ?? [],
            'last_updated' => null,
        ]);
    }

    public function setDailyRates(PosRateRequest $request): JsonResponse
    {
        $success = $this->rateService->setDailyRates($request->input('rates'), $request->user()->id);

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Daily rates updated successfully' : 'Failed to update daily rates',
        ]);
    }

    public function copyYesterdayRates(): JsonResponse
    {
        $previousRates = $this->rateService->copyPreviousDayRates();

        if ($previousRates === null) {
            return response()->json(['success' => false, 'message' => 'No rates found for yesterday'], 404);
        }

        $success = $this->rateService->setDailyRates($previousRates, auth()->id());

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Previous day rates copied successfully' : 'Failed to copy rates',
            'rates' => $previousRates,
        ]);
    }

    public function getRateHistory(): JsonResponse
    {
        $days = request()->input('days', 7);
        $history = $this->rateService->getRateHistory($days);

        return response()->json(['history' => $history]);
    }
}
```

---

### Task 6: Create rate views
**Files:** resources/views/pos/rates/index.blade.php

- [ ] **Step 1: Create rate index view**
```php
@extends('layouts.app')

@section('title', 'POS Rates')

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Daily Exchange Rates</h5>
                    <div>
                        <button id="copyYesterdayBtn" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-copy"></i> Copy Yesterday's Rates
                        </button>
                        <button id="saveRatesBtn" class="btn btn-primary btn-sm">
                            <i class="fas fa-save"></i> Save Rates
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div id="ratesAlert" class="alert" style="display: none;"></div>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Currency</th>
                                <th>Buy Rate</th>
                                <th>Sell Rate</th>
                                <th>Mid Rate</th>
                            </tr>
                        </thead>
                        <tbody id="ratesTableBody">
                            <tr><td colspan="4" class="text-center">Loading rates...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
const currencies = ['USD', 'EUR', 'GBP', 'SGD', 'JPY', 'AUD', 'CAD', 'CHF'];

function loadTodayRates() {
    fetch('/pos/rates/today')
        .then(r => r.json())
        .then(data => renderRatesTable(data));
}

function renderRatesTable(data) {
    const tbody = document.getElementById('ratesTableBody');
    if (!data.rates || Object.keys(data.rates).length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center">No rates set. Enter rates or copy yesterday.</td></tr>';
        return;
    }
    tbody.innerHTML = currencies.map(c => {
        const rate = data.rates[c] || { buy: '', sell: '', mid: '' };
        return `<tr>
            <td><strong>${c}</strong></td>
            <td><input type="number" step="0.000001" class="form-control rate-input" data-currency="${c}" data-type="buy" value="${rate.buy}"></td>
            <td><input type="number" step="0.000001" class="form-control rate-input" data-currency="${c}" data-type="sell" value="${rate.sell}"></td>
            <td><input type="number" step="0.000001" class="form-control rate-input" data-currency="${c}" data-type="mid" value="${rate.mid}"></td>
        </tr>`;
    }).join('');
}

function saveRates() {
    const rates = {};
    document.querySelectorAll('.rate-input').forEach(input => {
        const currency = input.dataset.currency;
        const type = input.dataset.type;
        if (!rates[currency]) rates[currency] = {};
        rates[currency][type] = parseFloat(input.value) || 0;
    });
    fetch('/pos/rates/set', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify({ rates })
    }).then(r => r.json()).then(data => {
        showAlert(data.success ? 'success' : 'error', data.message);
        if (data.success) loadTodayRates();
    });
}

function copyYesterdayRates() {
    fetch('/pos/rates/copy-yesterday', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
    }).then(r => r.json()).then(data => {
        showAlert(data.success ? 'success' : 'error', data.message);
        if (data.success) loadTodayRates();
    });
}

function showAlert(type, message) {
    const alert = document.getElementById('ratesAlert');
    alert.className = `alert alert-${type}`;
    alert.textContent = message;
    alert.style.display = 'block';
    setTimeout(() => alert.style.display = 'none', 5000);
}

document.addEventListener('DOMContentLoaded', function() {
    loadTodayRates();
    document.getElementById('saveRatesBtn').addEventListener('click', saveRates);
    document.getElementById('copyYesterdayBtn').addEventListener('click', copyYesterdayRates);
});
</script>
@endpush
@endsection
```

---

### Task 7: Add POS routes
**Files:** routes/web.php

- [ ] **Step 1: Add routes to web.php**
```php
Route::middleware(['auth', 'session.timeout'])->group(function () {
    Route::prefix('pos')->name('pos.')->group(function () {
        Route::prefix('rates')->name('rates.')->group(function () {
            Route::get('/', [App\Modules\Pos\Controllers\PosRateController::class, 'index'])->name('index');
            Route::get('/today', [App\Modules\Pos\Controllers\PosRateController::class, 'getTodayRates'])->name('today');
            Route::post('/set', [App\Modules\Pos\Controllers\PosRateController::class, 'setDailyRates'])->name('set');
            Route::post('/copy-yesterday', [App\Modules\Pos\Controllers\PosRateController::class, 'copyYesterdayRates'])->name('copy-yesterday');
            Route::get('/history', [App\Modules\Pos\Controllers\PosRateController::class, 'getRateHistory'])->name('history');
        });
    });
});
```

---

### Task 8: Write PosRateService tests
**Files:** tests/Unit/Pos/PosRateServiceTest.php

- [ ] **Step 1: Create test**
```php
<?php
namespace Tests\Unit\Pos;

use App\Modules\Pos\Models\PosDailyRate;
use App\Modules\Pos\Services\PosRateService;
use App\Services\MathService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosRateServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PosRateService $rateService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rateService = new PosRateService(new MathService());
    }

    public function test_get_today_rates_returns_null_when_no_rates_set()
    {
        $this->assertNull($this->rateService->getTodayRates());
    }

    public function test_get_today_rates_returns_rates_when_set()
    {
        $user = \App\Models\User::factory()->create();
        PosDailyRate::factory()->create([
            'rate_date' => today()->toDateString(),
            'currency_code' => 'USD',
            'buy_rate' => 4.6500,
            'sell_rate' => 4.7500,
            'mid_rate' => 4.7000,
            'created_by' => $user->id,
        ]);

        $rates = $this->rateService->getTodayRates();

        $this->assertNotNull($rates);
        $this->assertArrayHasKey('USD', $rates);
        $this->assertEquals('4.650000', $rates['USD']['buy']);
    }

    public function test_set_daily_rates_stores_rates_correctly()
    {
        $user = \App\Models\User::factory()->create();
        $rates = ['USD' => ['buy' => 4.6500, 'sell' => 4.7500, 'mid' => 4.7000]];

        $result = $this->rateService->setDailyRates($rates, $user->id);

        $this->assertTrue($result);
        $this->assertDatabaseHas('pos_daily_rates', [
            'rate_date' => today()->toDateString(),
            'currency_code' => 'USD',
            'buy_rate' => 4.6500,
        ]);
    }

    public function test_copy_previous_day_rates_copies_correctly()
    {
        $user = \App\Models\User::factory()->create();
        PosDailyRate::factory()->create([
            'rate_date' => \Carbon\Carbon::yesterday()->toDateString(),
            'currency_code' => 'USD',
            'buy_rate' => 4.6500,
            'sell_rate' => 4.7500,
            'mid_rate' => 4.7000,
            'created_by' => $user->id,
        ]);

        $rates = $this->rateService->copyPreviousDayRates();

        $this->assertNotNull($rates);
        $this->assertEquals('4.650000', $rates['USD']['buy']);
    }
}
```

---

### Task 9: Write PosRateController tests
**Files:** tests/Feature/Pos/PosRateControllerTest.php

- [ ] **Step 1: Create test**
```php
<?php
namespace Tests\Feature\Pos;

use App\Modules\Pos\Models\PosDailyRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosRateControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_view_with_rates()
    {
        $user = User::factory()->create();
        PosDailyRate::factory()->create([
            'rate_date' => today()->toDateString(),
            'currency_code' => 'USD',
            'buy_rate' => 4.6500,
            'sell_rate' => 4.7500,
            'mid_rate' => 4.7000,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('pos.rates.index'));

        $response->assertStatus(200);
        $response->assertViewIs('pos.rates.index');
    }

    public function test_get_today_rates_returns_json()
    {
        $user = User::factory()->create();
        PosDailyRate::factory()->create([
            'rate_date' => today()->toDateString(),
            'currency_code' => 'USD',
            'buy_rate' => 4.6500,
            'sell_rate' => 4.7500,
            'mid_rate' => 4.7000,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->getJson(route('pos.rates.today'));

        $response->assertStatus(200)->assertJsonStructure(['date', 'rates']);
    }

    public function test_set_daily_rates_requires_authentication()
    {
        $response = $this->postJson(route('pos.rates.set'), ['rates' => []]);
        $response->assertStatus(401);
    }

    public function test_set_daily_rates_stores_rates()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('pos.rates.set'), [
            'rates' => ['USD' => ['buy' => 4.6500, 'sell' => 4.7500, 'mid' => 4.7000]],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('pos_daily_rates', ['currency_code' => 'USD', 'buy_rate' => 4.6500]);
    }
}
```

---

## Phase 2: Transaction Processing

### Task 10: Create pos_receipts migration
**Files:** database/migrations/

- [ ] **Step 1: Run migration generator**
```bash
php artisan make:migration create_pos_receipts_table
```

- [ ] **Step 2: Edit migration**
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->onDelete('cascade');
            $table->string('receipt_number')->unique();
            $table->enum('receipt_type', ['thermal', 'pdf']);
            $table->string('template_type');
            $table->json('receipt_data');
            $table->timestamp('printed_at')->nullable();
            $table->foreignId('printed_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->index('transaction_id');
            $table->index('receipt_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_receipts');
    }
};
```

---

### Task 11: Create PosReceipt model
**Files:** app/Modules/Pos/Models/PosReceipt.php

- [ ] **Step 1: Create model**
```php
<?php
namespace App\Modules\Pos\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosReceipt extends Model
{
    use HasFactory;

    protected $table = 'pos_receipts';

    protected $fillable = [
        'transaction_id', 'receipt_number', 'receipt_type', 'template_type', 'receipt_data', 'printed_at', 'printed_by',
    ];

    protected $casts = ['receipt_data' => 'array', 'printed_at' => 'datetime'];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Transaction::class);
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'printed_by');
    }
}
```

- [ ] **Step 2: Create factory**
```bash
php artisan make:factory PosReceiptFactory --model=App\\Modules\\Pos\\Models\\PosReceipt
```

---

### Task 12: Create PosTransactionService
**Files:** app/Modules/Pos/Services/PosTransactionService.php

- [ ] **Step 1: Create service**
```php
<?php
namespace App\Modules\Pos\Services;

use App\Models\Counter;
use App\Models\Customer;
use App\Models\Transaction;
use App\Services\ComplianceService;
use App\Services\CurrencyPositionService;
use App\Services\MathService;
use App\Services\TransactionService;

class PosTransactionService
{
    protected TransactionService $transactionService;
    protected ComplianceService $complianceService;
    protected CurrencyPositionService $positionService;
    protected PosRateService $rateService;
    protected MathService $mathService;

    public function __construct(
        TransactionService $transactionService,
        ComplianceService $complianceService,
        CurrencyPositionService $positionService,
        PosRateService $rateService,
        MathService $mathService
    ) {
        $this->transactionService = $transactionService;
        $this->complianceService = $complianceService;
        $this->positionService = $positionService;
        $this->rateService = $rateService;
        $this->mathService = $mathService;
    }

    public function calculateQuote(array $data): array
    {
        $currencyCode = $data['currency_code'];
        $amountForeign = $data['amount_foreign'];
        $type = $data['type'];

        $rate = $this->rateService->getRateForCurrency($currencyCode);

        if ($rate === null) {
            throw new \RuntimeException("No rate set for {$currencyCode} today");
        }

        $rateValue = $type === 'Buy' ? $rate['buy'] : $rate['sell'];
        $amountLocal = $this->mathService->bcmul($amountForeign, $rateValue, 2);

        return [
            'amount_local' => $amountLocal,
            'rate' => $rateValue,
            'cdd_level' => $this->determineCddLevel($amountLocal),
            'compliance_flags' => [],
            'warnings' => [],
        ];
    }

    public function validateTransaction(array $data): array
    {
        $errors = [];
        $warnings = [];

        $counter = Counter::where('code', $data['till_id'])->first();

        if ($counter === null) {
            $errors[] = 'Counter not found';
        } elseif ($counter->status !== 'open') {
            $errors[] = 'Counter is not open';
        }

        $customer = Customer::find($data['customer_id'] ?? 0);

        if ($customer === null) {
            $errors[] = 'Customer not found';
        } elseif ($customer->sanction_match) {
            $errors[] = 'Customer is on sanctions list';
        } elseif ($customer->risk_rating === 'High') {
            $warnings[] = 'Customer has high risk rating';
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    public function createTransaction(array $data): Transaction
    {
        $quote = $this->calculateQuote($data);
        $validation = $this->validateTransaction($data);

        if (!empty($validation['errors'])) {
            throw new \RuntimeException(implode(', ', $validation['errors']));
        }

        $transactionData = [
            'type' => $data['type'],
            'currency_code' => $data['currency_code'],
            'amount_foreign' => $data['amount_foreign'],
            'amount_local' => $quote['amount_local'],
            'rate' => $quote['rate'],
            'customer_id' => $data['customer_id'],
            'till_id' => $data['till_id'],
            'purpose' => $data['purpose'],
            'source_of_funds' => $data['source_of_funds'],
            'cdd_level' => $quote['cdd_level'],
            'status' => 'Completed',
            'created_by' => auth()->id(),
        ];

        return $this->transactionService->createTransaction($transactionData);
    }

    protected function determineCddLevel(string $amountLocal): string
    {
        $amount = floatval($amountLocal);

        if ($amount < 3000) {
            return 'Simplified';
        } elseif ($amount < 50000) {
            return 'Standard';
        } else {
            return 'Enhanced';
        }
    }

    public function getTransactionQuote(array $data): array
    {
        try {
            $quote = $this->calculateQuote($data);
            $validation = $this->validateTransaction($data);

            return ['success' => true, 'quote' => $quote, 'validation' => $validation];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
```

---

### Task 13: Create PosTransactionRequest and PosTransactionController
**Files:** app/Modules/Pos/Requests/PosTransactionRequest.php, app/Modules/Pos/Controllers/PosTransactionController.php

- [ ] **Step 1: Create PosTransactionRequest**
```php
<?php
namespace App\Modules\Pos\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PosTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'required|in:Buy,Sell',
            'currency_code' => 'required|string|size:3',
            'amount_foreign' => 'required|numeric|min:0.01',
            'customer_id' => 'required|exists:customers,id',
            'till_id' => 'required|string|max:50',
            'purpose' => 'required|string|max:255',
            'source_of_funds' => 'required|string|max:255',
        ];
    }
}
```

- [ ] **Step 2: Create PosTransactionController**
```php
<?php
namespace App\Modules\Pos\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Modules\Pos\Requests\PosTransactionRequest;
use App\Modules\Pos\Services\PosTransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PosTransactionController extends Controller
{
    protected PosTransactionService $transactionService;

    public function __construct(PosTransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    public function index(): View
    {
        $transactions = Transaction::with(['customer', 'counter'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('pos.transaction.index', ['transactions' => $transactions]);
    }

    public function create(): View
    {
        $currencies = \App\Models\Currency::active()->get();
        $counters = \App\Models\Counter::where('status', 'open')->get();

        return view('pos.transaction.create', ['currencies' => $currencies, 'counters' => $counters]);
    }

    public function store(PosTransactionRequest $request): JsonResponse
    {
        try {
            $transaction = $this->transactionService->createTransaction($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Transaction created successfully',
                'transaction_id' => $transaction->id,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function show(Transaction $transaction): View
    {
        $transaction->load(['customer', 'counter', 'createdBy']);
        return view('pos.transaction.show', ['transaction' => $transaction]);
    }

    public function quote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|in:Buy,Sell',
            'currency_code' => 'required|string|size:3',
            'amount_foreign' => 'required|numeric|min:0.01',
            'customer_id' => 'nullable|exists:customers,id',
            'till_id' => 'nullable|string|max:50',
        ]);

        $result = $this->transactionService->getTransactionQuote($data);

        return response()->json($result);
    }
}
```

---

### Task 14: Create transaction views
**Files:** resources/views/pos/transaction/create.blade.php, show.blade.php

- [ ] **Step 1: Create transaction create view**
```php
@extends('layouts.app')

@section('title', 'New Transaction')

@section('content')
<div class="container-fluid py-4">
    <div class="card">
        <div class="card-header"><h5 class="mb-0">New Transaction</h5></div>
        <div class="card-body">
            <div id="transactionAlert" class="alert" style="display: none;"></div>
            <form id="transactionForm">
                @csrf
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Customer</label>
                        <input type="text" class="form-control" id="customerSearch" placeholder="Search by name, ID, or phone">
                        <input type="hidden" name="customer_id" id="customerId" required>
                        <div id="customerInfo" class="mt-2" style="display: none;"></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Counter</label>
                        <select class="form-select" name="till_id" id="tillSelect" required>
                            <option value="">Select Counter</option>
                            @foreach($counters as $counter)
                                <option value="{{ $counter->code }}">{{ $counter->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Type</label>
                        <select class="form-select" name="type" id="typeSelect" required>
                            <option value="">Select Type</option>
                            <option value="Buy">Buy</option>
                            <option value="Sell">Sell</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Currency</label>
                        <select class="form-select" name="currency_code" id="currencySelect" required>
                            <option value="">Select Currency</option>
                            @foreach($currencies as $currency)
                                <option value="{{ $currency->code }}">{{ $currency->code }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Foreign Amount</label>
                        <input type="number" step="0.01" class="form-control" name="amount_foreign" id="amountForeign" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Purpose</label>
                        <input type="text" class="form-control" name="purpose" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Source of Funds</label>
                        <input type="text" class="form-control" name="source_of_funds" required>
                    </div>
                </div>
                <div class="card bg-light mb-3">
                    <div class="card-body">
                        <h6>Quote</h6>
                        <div id="quoteDisplay"><p class="text-muted">Enter details to see quote</p></div>
                    </div>
                </div>
                <div class="d-flex justify-content-between">
                    <button type="button" id="calculateQuoteBtn" class="btn btn-outline-primary">Calculate Quote</button>
                    <button type="submit" id="submitBtn" class="btn btn-success" disabled>Create Transaction</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
let currentQuote = null;

function calculateQuote() {
    const type = document.getElementById('typeSelect').value;
    const currency = document.getElementById('currencySelect').value;
    const amount = document.getElementById('amountForeign').value;
    const customerId = document.getElementById('customerId').value;
    const tillId = document.getElementById('tillSelect').value;

    if (!type || !currency || !amount) return;

    fetch('/pos/transactions/quote', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify({ type, currency_code: currency, amount_foreign: parseFloat(amount), customer_id: customerId || null, till_id: tillId || null })
    }).then(r => r.json()).then(result => {
        if (result.success) {
            currentQuote = result;
            displayQuote(result.quote, result.validation);
            document.getElementById('submitBtn').disabled = result.validation.errors.length > 0;
        } else {
            showAlert('error', result.error);
        }
    });
}

function displayQuote(quote, validation) {
    let html = `<div class="row">
        <div class="col-md-4"><strong>Rate:</strong> ${quote.rate}</div>
        <div class="col-md-4"><strong>Local Amount:</strong> RM ${parseFloat(quote.amount_local).toFixed(2)}</div>
        <div class="col-md-4"><strong>CDD:</strong> ${quote.cdd_level}</div>
    </div>`;
    if (validation.warnings.length) {
        html += '<div class="alert alert-warning mt-2">' + validation.warnings.join('<br>') + '</div>';
    }
    document.getElementById('quoteDisplay').innerHTML = html;
}

function showAlert(type, message) {
    const alert = document.getElementById('transactionAlert');
    alert.className = `alert alert-${type}`;
    alert.textContent = message;
    alert.style.display = 'block';
    setTimeout(() => alert.style.display = 'none', 5000);
}

document.addEventListener('DOMContentLoaded', function() {
    ['typeSelect', 'currencySelect', 'amountForeign', 'tillSelect'].forEach(id => {
        document.getElementById(id).addEventListener('change', calculateQuote);
    });
    document.getElementById('calculateQuoteBtn').addEventListener('click', calculateQuote);
    document.getElementById('transactionForm').addEventListener('submit', function(e) {
        e.preventDefault();
        if (!currentQuote) return;
        const formData = new FormData(this);
        fetch('/pos/transactions', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: formData
        }).then(r => r.json()).then(data => {
            if (data.success) {
                showAlert('success', 'Transaction created');
                setTimeout(() => window.location.href = `/pos/transactions/${data.transaction_id}`, 1500);
            } else {
                showAlert('error', data.message);
            }
        });
    });
});
</script>
@endpush
@endsection
```

- [ ] **Step 2: Create transaction show view**
```php
@extends('layouts.app')

@section('title', 'Transaction Details')

@section('content')
<div class="container-fluid py-4">
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <h5 class="mb-0">Transaction #{{ $transaction->id }}</h5>
            <a href="/pos/transactions" class="btn btn-sm btn-secondary">Back</a>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6>Transaction</h6>
                    <table class="table table-sm">
                        <tr><th>Type:</th><td>{{ $transaction->type }}</td></tr>
                        <tr><th>Currency:</th><td>{{ $transaction->currency_code }}</td></tr>
                        <tr><th>Amount:</th><td>{{ number_format($transaction->amount_foreign, 2) }} {{ $transaction->currency_code }}</td></tr>
                        <tr><th>Rate:</th><td>{{ number_format($transaction->rate, 6) }}</td></tr>
                        <tr><th>Local:</th><td>RM {{ number_format($transaction->amount_local, 2) }}</td></tr>
                        <tr><th>Status:</th><td>{{ $transaction->status }}</td></tr>
                        <tr><th>CDD:</th><td>{{ $transaction->cdd_level }}</td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6>Customer</h6>
                    <table class="table table-sm">
                        <tr><th>Name:</th><td>{{ $transaction->customer->name ?? 'N/A' }}</td></tr>
                        <tr><th>Risk:</th><td>{{ $transaction->customer->risk_rating ?? 'N/A' }}</td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
```

---

### Task 15: Create PosReceiptService
**Files:** app/Modules/Pos/Services/PosReceiptService.php

- [ ] **Step 1: Create service**
```php
<?php
namespace App\Modules\Pos\Services;

use App\Models\Transaction;
use App\Modules\Pos\Models\PosReceipt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PosReceiptService
{
    protected string $storagePath;

    public function __construct()
    {
        $this->storagePath = config('pos.receipt_storage_path', storage_path('app/receipts'));
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
    }

    public function generateThermalReceipt(Transaction $transaction): string
    {
        $receiptData = $this->buildReceiptData($transaction);

        PosReceipt::create([
            'transaction_id' => $transaction->id,
            'receipt_number' => $this->generateReceiptNumber(),
            'receipt_type' => 'thermal',
            'template_type' => 'standard',
            'receipt_data' => $receiptData,
            'printed_at' => now(),
            'printed_by' => auth()->id(),
        ]);

        Log::info('POS receipt generated', ['transaction_id' => $transaction->id]);

        return $this->renderThermalReceipt($receiptData);
    }

    public function generatePdfReceipt(Transaction $transaction): string
    {
        $receiptData = $this->buildReceiptData($transaction);

        PosReceipt::create([
            'transaction_id' => $transaction->id,
            'receipt_number' => $this->generateReceiptNumber(),
            'receipt_type' => 'pdf',
            'template_type' => 'standard',
            'receipt_data' => $receiptData,
            'printed_at' => now(),
            'printed_by' => auth()->id(),
        ]);

        return $this->renderPdfReceipt($receiptData);
    }

    protected function buildReceiptData(Transaction $transaction): array
    {
        return [
            'receipt_number' => $this->generateReceiptNumber(),
            'transaction_id' => $transaction->id,
            'transaction_type' => $transaction->type,
            'currency_code' => $transaction->currency_code,
            'amount_foreign' => $transaction->amount_foreign,
            'amount_local' => $transaction->amount_local,
            'rate' => $transaction->rate,
            'customer_name' => $transaction->customer->name ?? 'N/A',
            'customer_id_masked' => $transaction->customer->id_number_masked ?? 'N/A',
            'counter_name' => $transaction->counter->name ?? 'N/A',
            'processed_by' => $transaction->createdBy->name ?? 'N/A',
            'processed_at' => $transaction->created_at->format('Y-m-d H:i:s'),
            'disclaimer' => $this->getBnmDisclaimer(),
        ];
    }

    protected function generateReceiptNumber(): string
    {
        return 'RCP-' . date('Ymd') . '-' . Str::upper(Str::random(6));
    }

    protected function renderThermalReceipt(array $data): string
    {
        return "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Receipt</title></head><body style='font-family:monospace;width:58mm;margin:0;padding:5px;'>
            <div style='text-align:center'><h3>CURRENCY EXCHANGE</h3></div>
            <div style='border-top:1px dashed #000;margin:5px 0;'></div>
            <div><strong>Receipt #:</strong> {$data['receipt_number']}</div>
            <div><strong>Date:</strong> {$data['processed_at']}</div>
            <div><strong>Type:</strong> {$data['transaction_type']}</div>
            <div><strong>Amount:</strong> {$data['amount_foreign']} {$data['currency_code']}</div>
            <div><strong>Rate:</strong> {$data['rate']}</div>
            <div><strong>Total:</strong> RM {$data['amount_local']}</div>
            <div style='border-top:1px dashed #000;margin:5px 0;'></div>
            <div><strong>Customer:</strong> {$data['customer_name']}</div>
            <div><strong>Counter:</strong> {$data['counter_name']}</div>
            <div style='border-top:1px dashed #000;margin:5px 0;'></div>
            <div style='font-size:10px'>" . nl2br(htmlspecialchars($data['disclaimer'])) . "</div>
        </body></html>";
    }

    protected function renderPdfReceipt(array $data): string
    {
        return "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Receipt - {$data['receipt_number']}</title></head><body style='font-family:Arial;margin:20px;'>
            <div style='text-align:center;border-bottom:2px solid #000;padding-bottom:20px;margin-bottom:20px;'>
                <h1>CURRENCY EXCHANGE RECEIPT</h1>
            </div>
            <h3>Transaction Details</h3>
            <table style='width:100%'>
                <tr><td>Receipt #:</td><td>{$data['receipt_number']}</td></tr>
                <tr><td>Date:</td><td>{$data['processed_at']}</td></tr>
                <tr><td>Type:</td><td>{$data['transaction_type']}</td></tr>
                <tr><td>Currency:</td><td>{$data['currency_code']}</td></tr>
                <tr><td>Amount:</td><td>{$data['amount_foreign']} {$data['currency_code']}</td></tr>
                <tr><td>Rate:</td><td>{$data['rate']}</td></tr>
                <tr><td><strong>Total (MYR):</strong></td><td><strong>RM {$data['amount_local']}</strong></td></tr>
            </table>
            <h3>Customer</h3>
            <p>{$data['customer_name']}<br>ID: {$data['customer_id_masked']}</p>
            <h3>Counter</h3>
            <p>{$data['counter_name']}<br>Processed By: {$data['processed_by']}</p>
            <div style='margin-top:30px;padding:10px;border:1px solid #ccc;font-size:10px;'>
                <strong>BNM Disclosures:</strong><br>" . nl2br(htmlspecialchars($data['disclaimer'])) . "
            </div>
        </body></html>";
    }

    protected function getBnmDisclaimer(): string
    {
        return "This transaction is conducted in accordance with Bank Negara Malaysia regulations.\n" .
               "All transactions above RM 3,000 require customer due diligence.\n" .
               "Transactions above RM 50,000 require enhanced due diligence.\n" .
               "Please retain this receipt for your records.";
    }

    public function getReceiptByTransaction(int $transactionId, string $type): ?PosReceipt
    {
        return PosReceipt::where('transaction_id', $transactionId)
            ->where('receipt_type', $type)->latest()->first();
    }
}
```

---

### Task 16: Create PosReceiptController
**Files:** app/Modules/Pos/Controllers/PosReceiptController.php

- [ ] **Step 1: Create controller**
```php
<?php
namespace App\Modules\Pos\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Modules\Pos\Services\PosReceiptService;
use Illuminate\Http\Response;

class PosReceiptController extends Controller
{
    protected PosReceiptService $receiptService;

    public function __construct(PosReceiptService $receiptService)
    {
        $this->receiptService = $receiptService;
    }

    public function thermal(Transaction $transaction): Response
    {
        $html = $this->receiptService->generateThermalReceipt($transaction);

        return new Response($html, 200, ['Content-Type' => 'text/html']);
    }

    public function pdf(Transaction $transaction): Response
    {
        $html = $this->receiptService->generatePdfReceipt($transaction);

        return new Response($html, 200, ['Content-Type' => 'text/html', 'Content-Disposition' => 'attachment; filename="receipt-' . $transaction->id . '.html"']);
    }
}
```

---

### Task 17: Add transaction and receipt routes
**Files:** routes/web.php

- [ ] **Step 1: Add routes**
```php
// Transactions
Route::prefix('transactions')->name('transactions.')->group(function () {
    Route::get('/', [App\Modules\Pos\Controllers\PosTransactionController::class, 'index'])->name('index');
    Route::get('/create', [App\Modules\Pos\Controllers\PosTransactionController::class, 'create'])->name('create');
    Route::post('/', [App\Modules\Pos\Controllers\PosTransactionController::class, 'store'])->name('store');
    Route::get('/{transaction}', [App\Modules\Pos\Controllers\PosTransactionController::class, 'show'])->name('show');
    Route::post('/quote', [App\Modules\Pos\Controllers\PosTransactionController::class, 'quote'])->name('quote');
});

// Receipts
Route::prefix('receipts')->name('receipts.')->group(function () {
    Route::get('/{transaction}/thermal', [App\Modules\Pos\Controllers\PosReceiptController::class, 'thermal'])->name('thermal');
    Route::get('/{transaction}/pdf', [App\Modules\Pos\Controllers\PosReceiptController::class, 'pdf'])->name('pdf');
});
```

---

### Task 18: Write transaction and receipt tests
**Files:** tests/Unit/Pos/PosTransactionServiceTest.php, tests/Unit/Pos/PosReceiptServiceTest.php

- [ ] **Step 1: Create PosTransactionServiceTest**
```php
<?php
namespace Tests\Unit\Pos;

use App\Models\Counter;
use App\Models\Customer;
use App\Models\Currency;
use App\Models\User;
use App\Modules\Pos\Models\PosDailyRate;
use App\Modules\Pos\Services\PosTransactionService;
use App\Services\MathService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosTransactionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PosTransactionService $transactionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transactionService = new PosTransactionService(
            new \App\Services\TransactionService(),
            new \App\Services\ComplianceService(),
            new \App\Services\CurrencyPositionService(),
            new \App\Modules\Pos\Services\PosRateService(new MathService()),
            new MathService()
        );
    }

    public function test_calculate_quote_returns_correct_amount()
    {
        $user = User::factory()->create();
        PosDailyRate::factory()->create([
            'rate_date' => today()->toDateString(),
            'currency_code' => 'USD',
            'buy_rate' => 4.6500,
            'sell_rate' => 4.7500,
            'mid_rate' => 4.7000,
            'created_by' => $user->id,
        ]);

        $quote = $this->transactionService->calculateQuote([
            'type' => 'Buy',
            'currency_code' => 'USD',
            'amount_foreign' => 1000,
        ]);

        $this->assertEquals('4650.00', $quote['amount_local']);
        $this->assertEquals('4.650000', $quote['rate']);
    }

    public function test_calculate_quote_uses_sell_rate_for_sell_transaction()
    {
        $user = User::factory()->create();
        PosDailyRate::factory()->create([
            'rate_date' => today()->toDateString(),
            'currency_code' => 'USD',
            'buy_rate' => 4.6500,
            'sell_rate' => 4.7500,
            'mid_rate' => 4.7000,
            'created_by' => $user->id,
        ]);

        $quote = $this->transactionService->calculateQuote([
            'type' => 'Sell',
            'currency_code' => 'USD',
            'amount_foreign' => 1000,
        ]);

        $this->assertEquals('4750.00', $quote['amount_local']);
    }

    public function test_determine_cdd_level_simplified()
    {
        $reflection = new \ReflectionClass($this->transactionService);
        $method = $reflection->getMethod('determineCddLevel');
        $method->setAccessible(true);

        $result = $method->invoke($this->transactionService, '2500.00');
        $this->assertEquals('Simplified', $result);

        $result = $method->invoke($this->transactionService, '10000.00');
        $this->assertEquals('Standard', $result);

        $result = $method->invoke($this->transactionService, '50000.00');
        $this->assertEquals('Enhanced', $result);
    }
}
```

- [ ] **Step 2: Create PosReceiptServiceTest**
```php
<?php
namespace Tests\Unit\Pos;

use App\Models\Transaction;
use App\Modules\Pos\Services\PosReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosReceiptServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PosReceiptService $receiptService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->receiptService = new PosReceiptService();
    }

    public function test_generate_thermal_receipt_creates_record()
    {
        $transaction = Transaction::factory()->create();

        $html = $this->receiptService->generateThermalReceipt($transaction);

        $this->assertStringContainsString('CURRENCY EXCHANGE', $html);
        $this->assertDatabaseHas('pos_receipts', [
            'transaction_id' => $transaction->id,
            'receipt_type' => 'thermal',
        ]);
    }

    public function test_generate_receipt_includes_bnm_disclaimer()
    {
        $transaction = Transaction::factory()->create();

        $html = $this->receiptService->generateThermalReceipt($transaction);

        $this->assertStringContainsString('Bank Negara Malaysia', $html);
    }

    public function test_get_receipt_by_transaction_returns_receipt()
    {
        $transaction = Transaction::factory()->create();
        $this->receiptService->generateThermalReceipt($transaction);

        $receipt = $this->receiptService->getReceiptByTransaction($transaction->id, 'thermal');

        $this->assertNotNull($receipt);
    }
}
```

---

## Phase 3: Inventory Management

### Task 19: Create PosInventoryService
**Files:** app/Modules/Pos/Services/PosInventoryService.php

- [ ] **Step 1: Create service**
```php
<?php
namespace App\Modules\Pos\Services;

use App\Models\Counter;
use App\Models\CurrencyPosition;
use App\Services\CurrencyPositionService;
use App\Services\MathService;
use Illuminate\Support\Facades\Cache;

class PosInventoryService
{
    protected CurrencyPositionService $positionService;
    protected MathService $mathService;

    public function __construct(CurrencyPositionService $positionService, MathService $mathService)
    {
        $this->positionService = $positionService;
        $this->mathService = $mathService;
    }

    public function getInventoryByCounter(string $counterId): array
    {
        $cacheKey = "pos:inventory:counter:{$counterId}";

        return Cache::remember($cacheKey, 300, function () use ($counterId) {
            $positions = CurrencyPosition::where('till_id', $counterId)->with('currency')->get();

            return $positions->map(function ($position) {
                return [
                    'currency_code' => $position->currency_code,
                    'currency_name' => $position->currency->name ?? 'Unknown',
                    'balance' => $this->mathService->bcadd($position->balance, '0'),
                    'status' => $this->getStockStatus($position->balance),
                ];
            })->toArray();
        });
    }

    public function getAggregateInventory(): array
    {
        $cacheKey = 'pos:inventory:aggregate';

        return Cache::remember($cacheKey, 300, function () {
            $positions = CurrencyPosition::with('currency')->get()->groupBy('currency_code');
            $inventory = [];

            foreach ($positions as $currencyCode => $currencyPositions) {
                $totalBalance = '0';
                foreach ($currencyPositions as $position) {
                    $totalBalance = $this->mathService->bcadd($totalBalance, $position->balance);
                }
                $inventory[$currencyCode] = [
                    'currency_code' => $currencyCode,
                    'currency_name' => $currencyPositions->first()->currency->name ?? 'Unknown',
                    'total_balance' => $totalBalance,
                    'status' => $this->getStockStatus($totalBalance),
                ];
            }

            return array_values($inventory);
        });
    }

    public function getLowStockCurrencies(float $threshold = 10000.00): array
    {
        $inventory = $this->getAggregateInventory();

        return array_filter($inventory, function ($item) use ($threshold) {
            return $this->mathService->bccomp($item['total_balance'], $threshold) < 0;
        });
    }

    public function calculateEodVariance(array $physicalCounts): array
    {
        $variances = [];

        foreach ($physicalCounts as $count) {
            $currencyCode = $count['currency_code'];
            $physicalAmount = $count['amount'];
            $counterId = $count['counter_id'];

            $position = CurrencyPosition::where('till_id', $counterId)
                ->where('currency_code', $currencyCode)->first();

            if ($position === null) continue;

            $expectedBalance = $position->balance;
            $variance = $this->mathService->bcsub($physicalAmount, $expectedBalance);

            $variances[] = [
                'currency_code' => $currencyCode,
                'counter_id' => $counterId,
                'expected_balance' => $expectedBalance,
                'physical_count' => $physicalAmount,
                'variance' => $variance,
                'status' => $this->getVarianceStatus($variance),
            ];
        }

        return $variances;
    }

    protected function getStockStatus(string $balance): string
    {
        $balanceFloat = floatval($balance);
        if ($balanceFloat < 10000) return 'low';
        if ($balanceFloat < 25000) return 'medium';
        return 'normal';
    }

    protected function getVarianceStatus(string $variance): string
    {
        $varianceAbs = abs(floatval($variance));
        if ($varianceAbs >= config('pos.eod_variance_red', 500)) return 'red';
        if ($varianceAbs >= config('pos.eod_variance_yellow', 100)) return 'yellow';
        return 'green';
    }

    public function invalidateCache(?string $counterId = null): void
    {
        if ($counterId) Cache::forget("pos:inventory:counter:{$counterId}");
        Cache::forget('pos:inventory:aggregate');
    }
}
```

---

### Task 20: Create PosInventoryController
**Files:** app/Modules/Pos/Controllers/PosInventoryController.php

- [ ] **Step 1: Create controller**
```php
<?php
namespace App\Modules\Pos\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Pos\Services\PosInventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PosInventoryController extends Controller
{
    protected PosInventoryService $inventoryService;

    public function __construct(PosInventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    public function index(): View
    {
        $aggregateInventory = $this->inventoryService->getAggregateInventory();
        $lowStockCurrencies = $this->inventoryService->getLowStockCurrencies();

        return view('pos.inventory.index', [
            'aggregateInventory' => $aggregateInventory,
            'lowStockCurrencies' => $lowStockCurrencies,
        ]);
    }

    public function counter(Request $request): JsonResponse
    {
        $counterId = $request->input('counter_id');

        if (!$counterId) {
            return response()->json(['success' => false, 'message' => 'Counter ID required'], 400);
        }

        return response()->json([
            'success' => true,
            'inventory' => $this->inventoryService->getInventoryByCounter($counterId),
        ]);
    }

    public function aggregate(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'inventory' => $this->inventoryService->getAggregateInventory(),
        ]);
    }

    public function lowStock(): JsonResponse
    {
        $threshold = request()->input('threshold', 10000);

        return response()->json([
            'success' => true,
            'low_stock' => $this->inventoryService->getLowStockCurrencies($threshold),
        ]);
    }

    public function eod(Request $request): JsonResponse
    {
        $data = $request->validate([
            'counter_id' => 'required|string|exists:counters,code',
            'physical_counts' => 'required|array',
            'physical_counts.*.currency_code' => 'required|string|size:3',
            'physical_counts.*.amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        $physicalCounts = array_map(function ($count) use ($data) {
            return [
                'currency_code' => $count['currency_code'],
                'amount' => $count['amount'],
                'counter_id' => $data['counter_id'],
            ];
        }, $data['physical_counts']);

        $variances = $this->inventoryService->calculateEodVariance($physicalCounts);

        $hasRedVariance = collect($variances)->contains('status', 'red');
        $hasYellowVariance = collect($variances)->contains('status', 'yellow');

        return response()->json([
            'success' => true,
            'variances' => $variances,
            'requires_manager_approval' => $hasRedVariance,
            'requires_notes' => $hasYellowVariance,
        ]);
    }
}
```

---

### Task 21: Create inventory views
**Files:** resources/views/pos/inventory/index.blade.php

- [ ] **Step 1: Create inventory index view**
```php
@extends('layouts.app')

@section('title', 'POS Inventory')

@section('content')
<div class="container-fluid py-4">
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <h5 class="mb-0">Inventory Dashboard</h5>
            <button id="refreshBtn" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-sync"></i> Refresh
            </button>
        </div>
        <div class="card-body">
            <div id="inventoryAlert" class="alert" style="display: none;"></div>
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h6>Total Currencies</h6>
                            <h3 id="totalCurrencies">-</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-warning bg-opacity-10">
                        <div class="card-body">
                            <h6>Low Stock</h6>
                            <h3 id="lowStockCount">-</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-success bg-opacity-10">
                        <div class="card-body">
                            <h6>Normal Stock</h6>
                            <h3 id="normalStockCount">-</h3>
                        </div>
                    </div>
                </div>
            </div>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Currency</th>
                        <th>Total Balance</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="inventoryTableBody">
                    <tr><td colspan="3" class="text-center">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
function loadInventory() {
    Promise.all([
        fetch('/pos/inventory/aggregate').then(r => r.json()),
        fetch('/pos/inventory/low-stock').then(r => r.json()),
    ]).then(([inv, low]) => {
        if (inv.success) renderTable(inv.inventory);
        if (inv.success && low.success) {
            document.getElementById('totalCurrencies').textContent = inv.inventory.length;
            document.getElementById('lowStockCount').textContent = low.low_stock.length;
            document.getElementById('normalStockCount').textContent = inv.inventory.length - low.low_stock.length;
        }
    });
}

function renderTable(inventory) {
    const tbody = document.getElementById('inventoryTableBody');
    if (!inventory.length) {
        tbody.innerHTML = '<tr><td colspan="3" class="text-center">No data</td></tr>';
        return;
    }
    tbody.innerHTML = inventory.map(item => {
        const cls = item.status === 'low' ? 'danger' : (item.status === 'medium' ? 'warning' : 'success');
        return `<tr>
            <td><strong>${item.currency_code}</strong> - ${item.currency_name}</td>
            <td>${parseFloat(item.total_balance).toLocaleString()} ${item.currency_code}</td>
            <td><span class="badge bg-${cls}">${item.status}</span></td>
        </tr>`;
    }).join('');
}

document.addEventListener('DOMContentLoaded', function() {
    loadInventory();
    document.getElementById('refreshBtn').addEventListener('click', loadInventory);
    setInterval(loadInventory, 60000);
});
</script>
@endpush
@endsection
```

---

### Task 22: Add inventory routes
**Files:** routes/web.php

- [ ] **Step 1: Add routes**
```php
// Inventory
Route::prefix('inventory')->name('inventory.')->group(function () {
    Route::get('/', [App\Modules\Pos\Controllers\PosInventoryController::class, 'index'])->name('index');
    Route::get('/counter', [App\Modules\Pos\Controllers\PosInventoryController::class, 'counter'])->name('counter');
    Route::get('/aggregate', [App\Modules\Pos\Controllers\PosInventoryController::class, 'aggregate'])->name('aggregate');
    Route::get('/low-stock', [App\Modules\Pos\Controllers\PosInventoryController::class, 'lowStock'])->name('low-stock');
    Route::post('/eod', [App\Modules\Pos\Controllers\PosInventoryController::class, 'eod'])->name('eod');
});
```

---

### Task 23: Write PosInventoryService tests
**Files:** tests/Unit/Pos/PosInventoryServiceTest.php

- [ ] **Step 1: Create test**
```php
<?php
namespace Tests\Unit\Pos;

use App\Models\Counter;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Modules\Pos\Services\PosInventoryService;
use App\Services\CurrencyPositionService;
use App\Services\MathService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosInventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PosInventoryService $inventoryService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryService = new PosInventoryService(new CurrencyPositionService(), new MathService());
    }

    public function test_get_inventory_by_counter_returns_inventory()
    {
        $counter = Counter::factory()->create();
        $currency = Currency::factory()->create();

        CurrencyPosition::factory()->create([
            'till_id' => $counter->code,
            'currency_code' => $currency->code,
            'balance' => 10000.00,
            'weighted_avg_cost' => 4.5000,
        ]);

        $inventory = $this->inventoryService->getInventoryByCounter($counter->code);

        $this->assertCount(1, $inventory);
        $this->assertEquals($currency->code, $inventory[0]['currency_code']);
    }

    public function test_get_aggregate_inventory_sums_across_counters()
    {
        $counter1 = Counter::factory()->create();
        $counter2 = Counter::factory()->create();
        $currency = Currency::factory()->create();

        CurrencyPosition::factory()->create([
            'till_id' => $counter1->code,
            'currency_code' => $currency->code,
            'balance' => 10000.00,
            'weighted_avg_cost' => 4.5000,
        ]);

        CurrencyPosition::factory()->create([
            'till_id' => $counter2->code,
            'currency_code' => $currency->code,
            'balance' => 5000.00,
            'weighted_avg_cost' => 4.6000,
        ]);

        $inventory = $this->inventoryService->getAggregateInventory();

        $this->assertCount(1, $inventory);
        $this->assertEquals('15000.000000', $inventory[0]['total_balance']);
    }

    public function test_get_low_stock_currencies_returns_low_stock_items()
    {
        $counter = Counter::factory()->create();
        $currency = Currency::factory()->create();

        CurrencyPosition::factory()->create([
            'till_id' => $counter->code,
            'currency_code' => $currency->code,
            'balance' => 5000.00,
            'weighted_avg_cost' => 4.5000,
        ]);

        $lowStock = $this->inventoryService->getLowStockCurrencies(10000);

        $this->assertCount(1, $lowStock);
    }

    public function test_calculate_eod_variance_returns_variance()
    {
        $counter = Counter::factory()->create();
        $currency = Currency::factory()->create();

        CurrencyPosition::factory()->create([
            'till_id' => $counter->code,
            'currency_code' => $currency->code,
            'balance' => 10000.00,
            'weighted_avg_cost' => 4.5000,
        ]);

        $variances = $this->inventoryService->calculateEodVariance([
            ['currency_code' => $currency->code, 'amount' => 10500, 'counter_id' => $counter->code],
        ]);

        $this->assertCount(1, $variances);
        $this->assertEquals('500.000000', $variances[0]['variance']);
        $this->assertEquals('green', $variances[0]['status']);
    }
}
```

---

### Task 24: Write PosInventoryController tests
**Files:** tests/Feature/Pos/PosInventoryControllerTest.php

- [ ] **Step 1: Create test**
```php
<?php
namespace Tests\Feature\Pos;

use App\Models\Counter;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosInventoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_view()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('pos.inventory.index'));

        $response->assertStatus(200);
        $response->assertViewIs('pos.inventory.index');
    }

    public function test_aggregate_returns_json()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson(route('pos.inventory.aggregate'));

        $response->assertStatus(200)->assertJsonStructure(['success', 'inventory']);
    }

    public function test_low_stock_returns_json()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson(route('pos.inventory.low-stock'));

        $response->assertStatus(200)->assertJsonStructure(['success', 'low_stock']);
    }

    public function test_eod_requires_authentication()
    {
        $response = $this->postJson(route('pos.inventory.eod'), [
            'counter_id' => 'TEST',
            'physical_counts' => [],
        ]);

        $response->assertStatus(401);
    }

    public function test_eod_validates_counter_exists()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('pos.inventory.eod'), [
            'counter_id' => 'NONEXISTENT',
            'physical_counts' => [['currency_code' => 'USD', 'amount' => 1000]],
        ]);

        $response->assertStatus(422);
    }
}
```

---

### Task 25: Run tests and verify
**Command to run:**
```bash
php artisan test --filter=PosRateServiceTest
php artisan test --filter=PosRateControllerTest
php artisan test --filter=PosTransactionServiceTest
php artisan test --filter=PosReceiptServiceTest
php artisan test --filter=PosInventoryServiceTest
php artisan test --filter=PosInventoryControllerTest
```

**Expected:** All tests pass

---

## Verification Checklist

- [ ] All migrations run successfully
- [ ] All models are created with proper relationships
- [ ] All services are created with proper dependencies
- [ ] All controllers handle requests and return responses
- [ ] All views render correctly
- [ ] All routes are registered
- [ ] All unit tests pass
- [ ] All feature tests pass
- [ ] POS module accessible at /pos routes
