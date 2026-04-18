# Sanction Screening Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Consolidate sanction screening services, add auto-import from external sources (UN, MOHA, OpenSanctions), fix threshold inconsistencies, and persist screening results.

**Architecture:** New `UnifiedSanctionScreeningService` replaces 3 existing services. `SanctionsImportService` handles background fetching from external sources. All screening results persisted to `screening_results` table. Transaction flow wired to use unified service.

**Tech Stack:** Laravel 10, MySQL, Guzzle HTTP, BCMath (via MathService), Laravel Queue

---

## File Structure

### New Files
- `app/Services/UnifiedSanctionScreeningService.php` - Consolidated screening
- `app/Services/SanctionsImportService.php` - Import from external sources
- `app/Http/Controllers/Api/V1/ScreeningController.php` - Screening endpoints
- `app/Http/Controllers/Api/V1/SanctionListController.php` - List management
- `app/Jobs/ImportSanctionsJob.php` - Background import
- `app/Jobs/RescreenHighRiskCustomersJob.php` - Background rescreening
- `app/Console/Commands/SanctionsImportCommand.php` - Manual import CLI
- `app/Models/SanctionList.php` - List model
- `config/sanctions.php` - Source configuration
- `database/migrations/2026_04_16_000001_create_sanction_import_logs_table.php`
- `database/migrations/2026_04_16_000002_add_list_source_to_sanction_entries.php`
- `tests/Unit/UnifiedSanctionScreeningServiceTest.php`
- `tests/Feature/ScreeningApiTest.php`

### Modified Files
- `app/Services/TransactionService.php` - Wire screening, persist results
- `app/Console/Kernel.php` - Add scheduled jobs
- `routes/api.php` - Add routes
- `app/Models/SanctionEntry.php` - Add source tracking
- `database/seeders/SanctionListSeeder.php` - Default sources

### Deleted Files
- `app/Services/SanctionScreeningService.php` - Replaced
- `app/Services/WatchlistApiService.php` - Replaced
- `app/Services/CustomerRiskScoringService.php` - Screening logic extracted

---

## Task 1: Database Migrations

**Files:**
- Create: `database/migrations/2026_04_16_000001_create_sanction_import_logs_table.php`
- Create: `database/migrations/2026_04_16_000002_add_list_source_to_sanction_entries.php`
- Modify: `app/Models/SanctionEntry.php`

- [ ] **Step 1: Create migration for sanction_import_logs table**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanction_import_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('list_id')->constrained('sanction_lists')->cascadeOnDelete();
            $table->timestamp('imported_at');
            $table->integer('records_added')->default(0);
            $table->integer('records_updated')->default(0);
            $table->integer('records_deactivated')->default(0);
            $table->enum('status', ['success', 'partial', 'failed'])->default('success');
            $table->text('error_message')->nullable();
            $table->enum('triggered_by', ['scheduled', 'manual'])->default('scheduled');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            
            $table->index(['list_id', 'imported_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanction_import_logs');
    }
};
```

- [ ] **Step 2: Create migration to add list_source to sanction_entries**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sanction_entries', function (Blueprint $table) {
            $table->foreignId('list_id')->nullable()->after('id')->constrained('sanction_lists')->cascadeOnDelete();
            $table->string('reference_number', 100)->nullable()->after('date_of_birth');
            $table->date('listing_date')->nullable()->after('reference_number');
        });
    }

    public function down(): void
    {
        Schema::table('sanction_entries', function (Blueprint $table) {
            $table->dropForeign(['list_id']);
            $table->dropColumn(['list_id', 'reference_number', 'listing_date']);
        });
    }
};
```

- [ ] **Step 3: Update SanctionEntry model**

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SanctionEntry extends Model
{
    protected $fillable = [
        'list_id',
        'entity_name',
        'entity_type',
        'aliases',
        'nationality',
        'date_of_birth',
        'reference_number',
        'listing_date',
        'details',
        'normalized_name',
        'soundex_code',
        'metaphone_code',
        'status',
    ];

    protected $casts = [
        'details' => 'array',
        'date_of_birth' => 'date',
        'listing_date' => 'date',
    ];

    public function list(): BelongsTo
    {
        return $this->belongsTo(SanctionList::class);
    }
}
```

- [ ] **Step 4: Create SanctionList model**

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SanctionList extends Model
{
    protected $fillable = [
        'name',
        'source_url',
        'source_format',
        'update_frequency',
        'last_synced_at',
        'status',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(SanctionEntry::class);
    }

    public function importLogs(): HasMany
    {
        return $this->hasMany(SanctionImportLog::class);
    }
}
```

- [ ] **Step 5: Create SanctionImportLog model**

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SanctionImportLog extends Model
{
    protected $fillable = [
        'list_id',
        'records_added',
        'records_updated',
        'records_deactivated',
        'status',
        'error_message',
        'triggered_by',
        'user_id',
    ];

    protected $casts = [
        'imported_at' => 'datetime',
    ];

    public function list(): BelongsTo
    {
        return $this->belongsTo(SanctionList::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 6: Run migrations**

```bash
php artisan migrate
```

- [ ] **Step 7: Commit**

```bash
git add database/migrations/ app/Models/SanctionEntry.php app/Models/SanctionList.php app/Models/SanctionImportLog.php
git commit -m "feat(sanctions): add database migrations for sanction lists and import logs"
```

---

## Task 2: Configuration and Seeder

**Files:**
- Create: `config/sanctions.php`
- Create: `database/seeders/SanctionListSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`

- [ ] **Step 1: Create config/sanctions.php**

```php
<?php

return [
    'sources' => [
        'un_consolidated' => [
            'name' => 'UN Security Council Consolidated',
            'url' => 'https://www.opensanctions.org/datasets/un_sc_sanctions/targets.nested.json',
            'format' => 'json',
            'frequency' => 'daily',
            'list_type' => 'international',
            'default_list' => true,
        ],
        'moha_malaysia' => [
            'name' => 'MOHA Malaysia Sanctions',
            'url' => 'https://www.opensanctions.org/datasets/my_moha_sanctions/targets.nested.json',
            'format' => 'json',
            'frequency' => 'weekly',
            'list_type' => 'national',
            'default_list' => true,
        ],
    ],

    'matching' => [
        'threshold_flag' => 75.0,
        'threshold_block' => 90.0,
        'algorithm' => 'levenshtein',
        'use_dob' => true,
        'use_nationality' => true,
        'max_candidates' => 100,
    ],

    'import' => [
        'timeout' => 300,
        'retry_attempts' => 3,
        'retry_delay' => 60,
        'fallback_continue' => true,
    ],
];
```

- [ ] **Step 2: Create SanctionListSeeder**

```php
<?php
namespace Database\Seeders;

use App\Models\SanctionList;
use Illuminate\Database\Seeder;

class SanctionListSeeder extends Seeder
{
    public function run(): void
    {
        $sources = config('sanctions.sources');

        foreach ($sources as $key => $source) {
            SanctionList::updateOrCreate(
                ['slug' => $key],
                [
                    'name' => $source['name'],
                    'source_url' => $source['url'],
                    'source_format' => $source['format'],
                    'update_frequency' => $source['frequency'],
                    'status' => $source['default_list'] ? 'active' : 'inactive',
                ]
            );
        }
    }
}
```

- [ ] **Step 3: Register seeder in DatabaseSeeder**

```php
public function run(): void
{
    $this->call([
        // ... existing seeders
        SanctionListSeeder::class,
    ]);
}
```

- [ ] **Step 4: Run seeder**

```bash
php artisan db:seed --class=SanctionListSeeder
```

- [ ] **Step 5: Commit**

```bash
git add config/sanctions.php database/seeders/SanctionListSeeder.php database/seeders/DatabaseSeeder.php
git commit -m "feat(sanctions): add config and seeder for sanction sources"
```

---

## Task 3: SanctionsImportService

**Files:**
- Create: `app/Services/SanctionsImportService.php`
- Create: `app/Jobs/ImportSanctionsJob.php`
- Create: `app/Console/Commands/SanctionsImportCommand.php`

- [ ] **Step 1: Write failing test for SanctionsImportService**

```php
<?php
namespace Tests\Unit;

use App\Models\SanctionList;
use App\Services\SanctionsImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SanctionsImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_creates_entries_from_json(): void
    {
        $list = SanctionList::factory()->create([
            'source_url' => 'https://example.com/test.json',
            'source_format' => 'json',
            'status' => 'active',
        ]);

        // Mock HTTP response
        $mockJson = json_encode([
            'results' => [
                [
                    'id' => 'test-1',
                    'name' => 'Test Sanctioned Person',
                    'entity_type' => 'Person',
                    'nationality' => 'IR',
                    'birth_date' => '1960-01-01',
                ]
            ]
        ]);

        Http::fake([
            '*' => Http::response($mockJson, 200),
        ]);

        $service = new SanctionsImportService();
        $result = $service->import($list);

        $this->assertEquals(1, $result['added']);
        $this->assertDatabaseHas('sanction_entries', [
            'entity_name' => 'TEST SANCTIONED PERSON',
            'nationality' => 'IR',
        ]);
    }

    public function test_import_updates_existing_entries(): void
    {
        $list = SanctionList::factory()->create();
        
        $existing = SanctionEntry::factory()->create([
            'list_id' => $list->id,
            'entity_name' => 'EXISTING NAME',
            'status' => 'active',
        ]);

        Http::fake([
            '*' => Http::response(json_encode([
                'results' => [
                    [
                        'id' => $existing->id,
                        'name' => 'EXISTING NAME UPDATED',
                        'entity_type' => 'Person',
                    ]
                ]
            ]), 200),
        ]);

        $service = new SanctionsImportService();
        $result = $service->import($list);

        $this->assertEquals(0, $result['added']);
        $this->assertEquals(1, $result['updated']);
    }

    public function test_import_deactivates_missing_entries(): void
    {
        $list = SanctionList::factory()->create();
        
        $existing = SanctionEntry::factory()->create([
            'list_id' => $list->id,
            'entity_name' => 'OLD ENTRY',
            'status' => 'active',
        ]);

        Http::fake([
            '*' => Http::response(json_encode(['results' => []]), 200),
        ]);

        $service = new SanctionsImportService();
        $result = $service->import($list);

        $this->assertEquals(0, $result['added']);
        $this->assertEquals(0, $result['updated']);
        $this->assertEquals(1, $result['deactivated']);
        
        $this->assertDatabaseHas('sanction_entries', [
            'id' => $existing->id,
            'status' => 'inactive',
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --filter=SanctionsImportServiceTest
```

Expected: FAIL (class doesn't exist)

- [ ] **Step 3: Create SanctionsImportService**

```php
<?php
namespace App\Services;

use App\Models\SanctionImportLog;
use App\Models\SanctionList;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SanctionsImportService
{
    public function __construct(
        protected MathService $math,
    ) {}

    public function import(SanctionList $list, bool $manual = false): array
    {
        $log = SanctionImportLog::create([
            'list_id' => $list->id,
            'imported_at' => now(),
            'triggered_by' => $manual ? 'manual' : 'scheduled',
            'status' => 'success',
        ]);

        try {
            $data = $this->fetchSource($list->source_url);
            $entries = $this->parseEntries($data, $list);
            
            $result = $this->syncEntries($entries, $list);
            
            $log->update([
                'records_added' => $result['added'],
                'records_updated' => $result['updated'],
                'records_deactivated' => $result['deactivated'],
                'status' => 'success',
            ]);

            $list->update(['last_synced_at' => now()]);

            return $result;
        } catch (\Exception $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error('Sanctions import failed', [
                'list_id' => $list->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function fetchSource(string $url): array
    {
        $timeout = config('sanctions.import.timeout', 300);

        $response = Http::timeout($timeout)
            ->retry(3, 1000)
            ->get($url);

        if ($response->failed()) {
            throw new \Exception("Failed to fetch source: " . $response->status());
        }

        return $response->json();
    }

    protected function parseEntries(array $data, SanctionList $list): Collection
    {
        $entries = collect();

        // Handle OpenSanctions nested JSON format
        if (isset($data['results']) && is_array($data['results'])) {
            foreach ($data['results'] as $item) {
                $entries->push($this->parseOpenSanctionsEntry($item, $list));
            }
        }

        return $entries->filter();
    }

    protected function parseOpenSanctionsEntry(array $item, SanctionList $list): ?array
    {
        if (!isset($item['name'])) {
            return null;
        }

        $names = $item['name'];
        $primaryName = is_array($names) ? ($names[0] ?? $names) : $names;

        if (is_array($primaryName)) {
            $primaryName = $primaryName['name'] ?? 'UNKNOWN';
        }

        $aliases = [];
        if (isset($item['aliases']) && is_array($item['aliases'])) {
            foreach ($item['aliases'] as $alias) {
                if (is_array($alias)) {
                    $aliases[] = $alias['name'] ?? '';
                } else {
                    $aliases[] = $alias;
                }
            }
        }

        $dob = null;
        if (isset($item['birth_date'])) {
            $dob = $this->parseDate($item['birth_date']);
        }

        $nationality = null;
        if (isset($item['nationality'])) {
            $nationality = is_array($item['nationality']) 
                ? ($item['nationality'][0]['name'] ?? null) 
                : $item['nationality'];
        }

        return [
            'external_id' => $item['id'] ?? null,
            'entity_name' => strtoupper(trim($primaryName)),
            'entity_type' => $this->mapEntityType($item['entity_type'] ?? 'Entity'),
            'aliases' => implode(', ', array_filter($aliases)),
            'nationality' => $nationality,
            'date_of_birth' => $dob,
            'normalized_name' => $this->normalizeName($primaryName),
            'soundex_code' => soundex($primaryName),
            'metaphone_code' => metaphone($primaryName),
            'status' => 'active',
        ];
    }

    protected function parseDate(string $date): ?string
    {
        try {
            return \Carbon\Carbon::parse($date)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function normalizeName(string $name): string
    {
        $name = strtolower($name);
        $name = preg_replace('/[^\p{L}\s]/u', '', $name);
        $name = preg_replace('/\s+/', ' ', $name);
        return trim($name);
    }

    protected function mapEntityType(string $type): string
    {
        return match (strtolower($type)) {
            'person', 'individual' => 'Individual',
            default => 'Entity',
        };
    }

    protected function syncEntries(Collection $entries, SanctionList $list): array
    {
        $result = [
            'added' => 0,
            'updated' => 0,
            'deactivated' => 0,
        ];

        $existingByExternalId = $list->entries()
            ->whereNotNull('reference_number')
            ->pluck('id', 'reference_number')
            ->toArray();

        $processedExternalIds = [];

        foreach ($entries as $entryData) {
            $externalId = $entryData['external_id'] ?? null;
            $processedExternalIds[] = $externalId;

            if ($externalId && isset($existingByExternalId[$externalId])) {
                $existingEntry = $list->entries()->find($existingByExternalId[$externalId]);
                $existingEntry->update($entryData);
                $result['updated']++;
            } else {
                $list->entries()->create($entryData);
                $result['added']++;
            }
        }

        if (!empty($processedExternalIds)) {
            $toDeactivate = $list->entries()
                ->whereNotIn('reference_number', array_filter($processedExternalIds))
                ->where('status', 'active')
                ->pluck('id');

            if ($toDeactivate->isNotEmpty()) {
                $list->entries()
                    ->whereIn('id', $toDeactivate)
                    ->update(['status' => 'inactive']);
                $result['deactivated'] = $toDeactivate->count();
            }
        }

        return $result;
    }
}
```

- [ ] **Step 4: Create ImportSanctionsJob**

```php
<?php
namespace App\Jobs;

use App\Models\SanctionList;
use App\Services\SanctionsImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ImportSanctionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public ?SanctionList $list = null,
    ) {}

    public function handle(SanctionsImportService $service): void
    {
        $list = $this->list ?? SanctionList::where('status', 'active')->first();

        if (!$list) {
            Log::warning('ImportSanctionsJob: No active sanction list found');
            return;
        }

        try {
            $service->import($list);
            Log::info('ImportSanctionsJob: Completed', ['list_id' => $list->id]);
        } catch (\Exception $e) {
            Log::error('ImportSanctionsJob: Failed', [
                'list_id' => $list->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
```

- [ ] **Step 5: Create SanctionsImportCommand**

```php
<?php
namespace App\Console\Commands;

use App\Models\SanctionList;
use App\Services\SanctionsImportService;
use Illuminate\Console\Command;

class SanctionsImportCommand extends Command
{
    protected $signature = 'sanctions:import {--list=} {--all}';
    protected $description = 'Import sanctions from configured sources';

    public function handle(SanctionsImportService $service): int
    {
        $listId = $this->option('list');
        $all = $this->option('all');

        if ($listId) {
            $list = SanctionList::findOrFail($listId);
            $this->info("Importing from: {$list->name}");
            $result = $service->import($list, manual: true);
        } elseif ($all) {
            $lists = SanctionList::where('status', 'active')->get();
            foreach ($lists as $list) {
                $this->info("Importing from: {$list->name}");
                try {
                    $result = $service->import($list, manual: true);
                    $this->info("  Added: {$result['added']}, Updated: {$result['updated']}, Deactivated: {$result['deactivated']}");
                } catch (\Exception $e) {
                    $this->error("  Failed: {$e->getMessage()}");
                }
            }
        } else {
            $this->error('Specify --list=<id> or --all');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
```

- [ ] **Step 6: Run tests to verify**

```bash
php artisan test --filter=SanctionsImportServiceTest
```

- [ ] **Step 7: Commit**

```bash
git add app/Services/SanctionsImportService.php app/Jobs/ImportSanctionsJob.php app/Console/Commands/SanctionsImportCommand.php
git commit -m "feat(sanctions): add SanctionsImportService for external source imports"
```

---

## Task 4: UnifiedSanctionScreeningService

**Files:**
- Create: `app/Services/UnifiedSanctionScreeningService.php`
- Create: `app/Services/ScreeningResponse.php`
- Create: `app/Services/ScreeningMatch.php`
- Create: `tests/Unit/UnifiedSanctionScreeningServiceTest.php`

- [ ] **Step 1: Write failing tests for UnifiedSanctionScreeningService**

```php
<?php
namespace Tests\Unit;

use App\Models\Customer;
use App\Models\SanctionEntry;
use App\Models\SanctionList;
use App\Services\MathService;
use App\Services\UnifiedSanctionScreeningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnifiedSanctionScreeningServiceTest extends TestCase
{
    use RefreshDatabase;

    private UnifiedSanctionScreeningService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UnifiedSanctionScreeningService(
            new MathService()
        );
    }

    public function test_screen_name_returns_clear_for_no_match(): void
    {
        $response = $this->service->screenName('John Doe');

        $this->assertEquals('clear', $response->action);
        $this->assertEquals(0, $response->confidenceScore);
        $this->assertTrue($response->matches->isEmpty());
    }

    public function test_screen_name_returns_flag_for_partial_match(): void
    {
        $list = SanctionList::factory()->create();
        SanctionEntry::factory()->create([
            'list_id' => $list->id,
            'entity_name' => 'ABU BAKAR',
            'normalized_name' => 'abu bakar',
        ]);

        $response = $this->service->screenName('Abu Bakar bin Ahmad');

        $this->assertEquals('flag', $response->action);
        $this->assertGreaterThanOrEqual(75, $response->confidenceScore);
        $this->assertFalse($response->matches->isEmpty());
    }

    public function test_screen_name_uses_dob_for_confidence(): void
    {
        $list = SanctionList::factory()->create();
        SanctionEntry::factory()->create([
            'list_id' => $list->id,
            'entity_name' => 'AHMAD',
            'date_of_birth' => '1960-01-01',
            'normalized_name' => 'ahmad',
        ]);

        // With matching DOB - should have higher confidence
        $responseWithDob = $this->service->screenName('Ahmad bin Ali', dob: '1960-01-01');
        
        // With non-matching DOB - should still flag but with name match only
        $responseWithWrongDob = $this->service->screenName('Ahmad bin Ali', dob: '1990-01-01');

        $this->assertGreaterThanOrEqual(75, $responseWithDob->confidenceScore);
        $this->assertGreaterThan($responseWithWrongDob->confidenceScore, $responseWithDob->confidenceScore);
    }

    public function test_screen_customer_checks_existing_sanction_hit(): void
    {
        $customer = Customer::factory()->create(['sanction_hit' => true]);

        $response = $this->service->screenCustomer($customer);

        $this->assertEquals('flag', $response->action);
        $this->assertEquals(100.0, $response->confidenceScore);
    }

    public function test_screen_customer_persists_result(): void
    {
        $customer = Customer::factory()->create();
        
        $this->service->screenCustomer($customer);

        $this->assertDatabaseHas('screening_results', [
            'customer_id' => $customer->id,
            'screening_type' => 'manual',
        ]);
    }

    public function test_threshold_is_75_percent(): void
    {
        $list = SanctionList::factory()->create();
        
        // Create entry with 70% similarity
        SanctionEntry::factory()->create([
            'list_id' => $list->id,
            'entity_name' => 'JOHN SMITH',
            'normalized_name' => 'john smith',
        ]);

        $response = $this->service->screenName('John Smit');

        $this->assertEquals('clear', $response->action);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test --filter=UnifiedSanctionScreeningServiceTest
```

Expected: FAIL (classes don't exist)

- [ ] **Step 3: Create ScreeningMatch class**

```php
<?php
namespace App\Services;

use App\Models\SanctionEntry;
use Illuminate\Database\Eloquent\Model;

class ScreeningMatch
{
    public function __construct(
        public readonly int $entryId,
        public readonly string $entityName,
        public readonly string $listName,
        public readonly string $listSource,
        public readonly float $matchScore,
        public readonly array $matchedFields,
        public readonly ?string $listingDate,
        public readonly ?string $dateOfBirth,
        public readonly ?string $nationality,
    ) {}

    public static function fromEntry(SanctionEntry $entry, float $score, array $fields = ['name']): self
    {
        return new self(
            entryId: $entry->id,
            entityName: $entry->entity_name,
            listName: $entry->list?->name ?? 'Unknown',
            listSource: $entry->list?->source_url ?? 'manual',
            matchScore: $score,
            matchedFields: $fields,
            listingDate: $entry->listing_date?->format('Y-m-d'),
            dateOfBirth: $entry->date_of_birth?->format('Y-m-d'),
            nationality: $entry->nationality,
        );
    }

    public function toArray(): array
    {
        return [
            'entry_id' => $this->entryId,
            'entity_name' => $this->entityName,
            'list_name' => $this->listName,
            'list_source' => $this->listSource,
            'match_score' => $this->matchScore,
            'matched_fields' => $this->matchedFields,
            'listing_date' => $this->listingDate,
            'date_of_birth' => $this->dateOfBirth,
            'nationality' => $this->nationality,
        ];
    }
}
```

- [ ] **Step 4: Create ScreeningResponse class**

```php
<?php
namespace App\Services;

use App\Models\ScreeningResult;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ScreeningResponse
{
    public function __construct(
        public readonly string $action,         // clear, flag, block
        public readonly float $confidenceScore,  // 0.00 - 100.00
        public readonly Collection $matches,
        public readonly Carbon $screenedAt,
        public readonly ?int $resultId = null,
    ) {}

    public static function fromResult(ScreeningResult $result): self
    {
        $matches = collect();
        $matchData = $result->matched_entries ?? [];

        foreach ($matchData as $match) {
            $matches->push(new ScreeningMatch(
                entryId: $match['entry_id'] ?? 0,
                entityName: $match['entity_name'] ?? '',
                listName: $match['list_name'] ?? '',
                listSource: $match['list_source'] ?? '',
                matchScore: $match['match_score'] ?? 0,
                matchedFields: $match['matched_fields'] ?? ['name'],
                listingDate: $match['listing_date'] ?? null,
                dateOfBirth: $match['date_of_birth'] ?? null,
                nationality: $match['nationality'] ?? null,
            ));
        }

        return new self(
            action: $result->action,
            confidenceScore: (float) $result->match_score,
            matches: $matches,
            screenedAt: $result->screened_at,
            resultId: $result->id,
        );
    }

    public function isClear(): bool
    {
        return $this->action === 'clear';
    }

    public function isFlagged(): bool
    {
        return $this->action === 'flag';
    }

    public function isBlocked(): bool
    {
        return $this->action === 'block';
    }

    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'confidence_score' => round($this->confidenceScore, 2),
            'matches' => $this->matches->map(fn(ScreeningMatch $m) => $m->toArray())->toArray(),
            'screened_at' => $this->screenedAt->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 5: Create UnifiedSanctionScreeningService**

```php
<?php
namespace App\Services;

use App\Models\Customer;
use App\Models\ScreeningResult;
use App\Models\SanctionEntry;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UnifiedSanctionScreeningService
{
    protected float $thresholdFlag;
    protected float $thresholdBlock;
    protected bool $useDob;
    protected bool $useNationality;
    protected int $maxCandidates;

    public function __construct(
        protected MathService $math,
    ) {
        $this->thresholdFlag = (float) config('sanctions.matching.threshold_flag', 75.0);
        $this->thresholdBlock = (float) config('sanctions.matching.threshold_block', 90.0);
        $this->useDob = config('sanctions.matching.use_dob', true);
        $this->useNationality = config('sanctions.matching.use_nationality', true);
        $this->maxCandidates = (int) config('sanctions.matching.max_candidates', 100);
    }

    public function screenCustomer(Customer $customer, ?string $notes = null): ScreeningResponse
    {
        // If already marked as sanction hit, flag immediately
        if ($customer->sanction_hit) {
            $entry = $customer->sanctionEntries()->first();
            $match = $entry 
                ? ScreeningMatch::fromEntry($entry, 100.0, ['name'])
                : null;

            $matches = $match ? collect([$match]) : collect();

            return $this->createResult(
                customer: $customer,
                action: 'flag',
                confidenceScore: 100.0,
                matches: $matches,
                notes: $notes,
            );
        }

        return $this->screenName(
            name: $customer->full_name,
            dob: $customer->date_of_birth?->format('Y-m-d'),
            nationality: $customer->nationality,
            customerId: $customer->id,
            notes: $notes,
        );
    }

    public function screenName(
        string $name,
        ?string $dob = null,
        ?string $nationality = null,
        ?int $customerId = null,
        ?string $notes = null,
    ): ScreeningResponse {
        $normalizedInput = $this->normalizeName($name);
        $inputTokens = $this->tokenize($normalizedInput);

        $candidates = $this->findCandidates($normalizedInput);

        $matches = collect();
        $highestScore = 0.0;

        foreach ($candidates as $entry) {
            $score = $this->calculateMatchScore(
                entry: $entry,
                normalizedInput: $normalizedInput,
                inputTokens: $inputTokens,
                dob: $dob,
                nationality: $nationality,
            );

            if ($score > $highestScore) {
                $highestScore = $score;
            }

            if ($score >= $this->thresholdFlag) {
                $matchedFields = ['name'];
                
                // Check DOB match if available
                if ($this->useDob && $dob && $entry->date_of_birth) {
                    if ($this->datesMatch($dob, $entry->date_of_birth->format('Y-m-d'))) {
                        $matchedFields[] = 'dob';
                    }
                }

                // Check nationality match if available
                if ($this->useNationality && $nationality && $entry->nationality) {
                    if ($this->nationalitiesMatch($nationality, $entry->nationality)) {
                        $matchedFields[] = 'nationality';
                    }
                }

                $matches->push(ScreeningMatch::fromEntry($entry, $score, $matchedFields));
            }
        }

        // Sort matches by score descending
        $matches = $matches->sortByDesc(fn($m) => $m->matchScore)->values();

        $action = $highestScore >= $this->thresholdFlag ? 'flag' : 'clear';

        return $this->createResult(
            customerId: $customerId,
            action: $action,
            confidenceScore: $highestScore,
            matches: $matches,
            notes: $notes,
        );
    }

    public function screenTransaction(Transaction $transaction): ScreeningResponse
    {
        $customer = $transaction->customer;

        return $this->screenCustomer(
            customer: $customer,
            notes: "Transaction {$transaction->id}",
        );
    }

    public function batchScreen(array $customerIds): Collection
    {
        $results = collect();

        foreach ($customerIds as $customerId) {
            $customer = Customer::find($customerId);
            if ($customer) {
                $results->put($customerId, $this->screenCustomer($customer));
            }
        }

        return $results;
    }

    public function getHistory(Customer $customer): Collection
    {
        return ScreeningResult::where('customer_id', $customer->id)
            ->orderBy('screened_at', 'desc')
            ->get()
            ->map(fn($result) => ScreeningResponse::fromResult($result));
    }

    public function getStatus(Customer $customer): array
    {
        $lastResult = ScreeningResult::where('customer_id', $customer->id)
            ->orderBy('screened_at', 'desc')
            ->first();

        return [
            'customer_id' => $customer->id,
            'last_screened_at' => $lastResult?->screened_at?->toIso8601String(),
            'last_action' => $lastResult?->action,
            'last_score' => $lastResult ? (float) $lastResult->match_score : null,
            'is_sanctioned' => $customer->sanction_hit,
        ];
    }

    protected function findCandidates(string $normalizedName): Collection
    {
        $likePattern = '%' . $this->escapeLike($normalizedName) . '%';

        return SanctionEntry::with('list')
            ->where('status', 'active')
            ->whereHas('list', fn($q) => $q->where('status', 'active'))
            ->where(function ($q) use ($likePattern) {
                $q->where('normalized_name', 'like', $likePattern)
                  ->orWhereRaw("aliases LIKE ?", [$likePattern]);
            })
            ->limit($this->maxCandidates)
            ->get();
    }

    protected function calculateMatchScore(
        SanctionEntry $entry,
        string $normalizedInput,
        array $inputTokens,
        ?string $dob,
        ?string $nationality,
    ): float {
        $scores = [];

        // Levenshtein similarity on normalized names
        $levScore = $this->levenshteinSimilarity(
            $normalizedInput,
            $entry->normalized_name ?? ''
        );
        $scores[] = $levScore * 100;

        // Token match score
        $entryTokens = $this->tokenize($entry->normalized_name ?? '');
        $tokenScore = $this->tokenMatchScore($inputTokens, $entryTokens);
        $scores[] = $tokenScore * 100;

        // Phonetic match (soundex)
        if (soundex($normalizedInput) === $entry->soundex_code) {
            $scores[] = 85.0;
        }

        // Phonetic match (metaphone)
        if (metaphone($normalizedInput) === $entry->metaphone_code) {
            $scores[] = 85.0;
        }

        // Alias matching
        if ($entry->aliases) {
            $aliasList = explode(',', $entry->aliases);
            foreach ($aliasList as $alias) {
                $alias = trim($alias);
                if (empty($alias)) continue;

                $normalizedAlias = $this->normalizeName($alias);
                $aliasScore = $this->levenshteinSimilarity($normalizedInput, $normalizedAlias);
                $scores[] = $aliasScore * 100;
            }
        }

        return max($scores);
    }

    protected function levenshteinSimilarity(string $a, string $b): float
    {
        if (empty($a) || empty($b)) {
            return 0.0;
        }

        $maxLen = max(strlen($a), strlen($b));
        
        return 1.0 - (levenshtein($a, $b) / $maxLen);
    }

    protected function tokenize(string $text): array
    {
        return array_filter(explode(' ', strtolower($text)));
    }

    protected function tokenMatchScore(array $tokens1, array $tokens2): float
    {
        if (empty($tokens1) || empty($tokens2)) {
            return 0.0;
        }

        $intersection = array_intersect($tokens1, $tokens2);
        $union = array_unique(array_merge($tokens1, $tokens2));

        return count($intersection) / count($union);
    }

    protected function datesMatch(string $date1, string $date2): bool
    {
        return substr($date1, 0, 4) === substr($date2, 0, 4) &&
               substr($date1, 5, 2) === substr($date2, 5, 2);
    }

    protected function nationalitiesMatch(string $nat1, string $nat2): bool
    {
        return strtoupper(substr($nat1, 0, 2)) === strtoupper(substr($nat2, 0, 2));
    }

    protected function normalizeName(string $name): string
    {
        $name = strtolower($name);
        $name = preg_replace('/[^\p{L}\s]/u', '', $name);
        $name = preg_replace('/\s+/', ' ', $name);
        return trim($name);
    }

    protected function escapeLike(string $value): string
    {
        return str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $value);
    }

    protected function createResult(
        ?Customer $customer = null,
        ?int $customerId = null,
        string $action = 'clear',
        float $confidenceScore = 0.0,
        Collection $matches = null,
        ?string $notes = null,
    ): ScreeningResponse {
        $customerId = $customerId ?? $customer?->id;

        $matchData = $matches?->map(fn(ScreeningMatch $m) => $m->toArray())->toArray() ?? [];

        $result = ScreeningResult::create([
            'customer_id' => $customerId,
            'screening_type' => 'manual',
            'action' => $action,
            'match_score' => $confidenceScore,
            'matched_entries' => $matchData,
            'screened_at' => now(),
            'notes' => $notes,
        ]);

        return ScreeningResponse::fromResult($result);
    }
}
```

- [ ] **Step 6: Run tests to verify they pass**

```bash
php artisan test --filter=UnifiedSanctionScreeningServiceTest
```

- [ ] **Step 7: Commit**

```bash
git add app/Services/UnifiedSanctionScreeningService.php app/Services/ScreeningResponse.php app/Services/ScreeningMatch.php
git commit -m "feat(sanctions): add UnifiedSanctionScreeningService with consolidated matching"
```

---

## Task 5: API Controllers

**Files:**
- Create: `app/Http/Controllers/Api/V1/ScreeningController.php`
- Create: `app/Http/Controllers/Api/V1/SanctionListController.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Write failing tests for ScreeningController**

```php
<?php
namespace Tests\Feature;

use App\Models\Customer;
use App\Models\SanctionList;
use App\Models\SanctionEntry;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScreeningApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_screen_customer_requires_authentication(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->postJson("/api/v1/screening/customer/{$customer->id}");

        $response->assertStatus(401);
    }

    public function test_screen_customer_requires_compliance_role(): void
    {
        $customer = Customer::factory()->create();
        $user = User::factory()->create(['role' => UserRole::Teller]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/screening/customer/{$customer->id}");

        $response->assertStatus(403);
    }

    public function test_screen_customer_returns_flag_for_match(): void
    {
        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        Sanctum::actingAs($user);

        $list = SanctionList::factory()->create();
        SanctionEntry::factory()->create([
            'list_id' => $list->id,
            'entity_name' => 'TEST TERRORIST',
        ]);

        $customer = Customer::factory()->create([
            'full_name' => 'Test Terrorist',
        ]);

        $response = $this->postJson("/api/v1/screening/customer/{$customer->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'action' => 'flag',
                    'confidence_score' => 100.0,
                ],
            ]);
    }

    public function test_get_screening_history(): void
    {
        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        Sanctum::actingAs($user);

        $customer = Customer::factory()->create();

        // Create existing screening result
        ScreeningResult::factory()->count(3)->create([
            'customer_id' => $customer->id,
        ]);

        $response = $this->getJson("/api/v1/screening/customer/{$customer->id}/history");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_get_screening_status(): void
    {
        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        Sanctum::actingAs($user);

        $customer = Customer::factory()->create();

        $response = $this->getJson("/api/v1/screening/customer/{$customer->id}/status");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'customer_id',
                    'last_screened_at',
                    'last_action',
                    'last_score',
                    'is_sanctioned',
                ],
            ]);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test --filter=ScreeningApiTest
```

Expected: FAIL (controllers don't exist)

- [ ] **Step 3: Create ScreeningController**

```php
<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\UnifiedSanctionScreeningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ScreeningController extends Controller
{
    public function __construct(
        protected UnifiedSanctionScreeningService $screeningService,
    ) {}

    public function screen(Request $request, int $customerId): JsonResponse
    {
        $customer = Customer::findOrFail($customerId);

        $notes = $request->input('notes');

        $response = $this->screeningService->screenCustomer($customer, $notes);

        return response()->json([
            'data' => $response->toArray(),
        ]);
    }

    public function history(Request $request, int $customerId): JsonResponse
    {
        $customer = Customer::findOrFail($customerId);

        $history = $this->screeningService->getHistory($customer);

        return response()->json([
            'data' => $history->map(fn($r) => $r->toArray())->toArray(),
        ]);
    }

    public function status(Request $request, int $customerId): JsonResponse
    {
        $customer = Customer::findOrFail($customerId);

        $status = $this->screeningService->getStatus($customer);

        return response()->json([
            'data' => $status,
        ]);
    }

    public function batchScreen(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_ids' => 'required|array|min:1|max:100',
            'customer_ids.*' => 'integer|exists:customers,id',
        ]);

        $results = $this->screeningService->batchScreen($validated['customer_ids']);

        return response()->json([
            'data' => $results->map(fn($r, $id) => array_merge(['customer_id' => $id], $r->toArray()))->values()->toArray(),
        ]);
    }
}
```

- [ ] **Step 4: Create SanctionListController**

```php
<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SanctionList;
use App\Models\SanctionEntry;
use App\Services\SanctionsImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SanctionListController extends Controller
{
    public function __construct(
        protected SanctionsImportService $importService,
    ) {}

    public function lists(): JsonResponse
    {
        $lists = SanctionList::withCount('entries')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $lists->map(fn($list) => [
                'id' => $list->id,
                'name' => $list->name,
                'source_url' => $list->source_url,
                'source_format' => $list->source_format,
                'update_frequency' => $list->update_frequency,
                'last_synced_at' => $list->last_synced_at?->toIso8601String(),
                'status' => $list->status,
                'entries_count' => $list->entries_count,
            ])->toArray(),
        ]);
    }

    public function entries(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'list_id' => 'integer|exists:sanction_lists,id',
            'search' => 'string|max:255',
            'status' => 'in:active,inactive,all',
        ]);

        $perPage = $validated['per_page'] ?? 50;
        $status = $validated['status'] ?? 'active';

        $query = SanctionEntry::with('list')
            ->when($validated['list_id'] ?? null, fn($q, $id) => $q->where('list_id', $id))
            ->when($validated['search'] ?? null, fn($q, $search) => $q->where('entity_name', 'like', "%{$search}%"))
            ->when($status !== 'all', fn($q) => $q->where('status', $status))
            ->orderBy('entity_name');

        $entries = $query->paginate($perPage);

        return response()->json([
            'data' => $entries->map(fn($entry) => [
                'id' => $entry->id,
                'entity_name' => $entry->entity_name,
                'entity_type' => $entry->entity_type,
                'list' => [
                    'id' => $entry->list?->id,
                    'name' => $entry->list?->name,
                ],
                'nationality' => $entry->nationality,
                'date_of_birth' => $entry->date_of_birth?->format('Y-m-d'),
                'reference_number' => $entry->reference_number,
                'status' => $entry->status,
                'listing_date' => $entry->listing_date?->format('Y-m-d'),
            ]),
            'meta' => [
                'current_page' => $entries->currentPage(),
                'per_page' => $entries->perPage(),
                'total' => $entries->total(),
            ],
        ]);
    }

    public function triggerImport(Request $request, int $listId): JsonResponse
    {
        $list = SanctionList::findOrFail($listId);

        try {
            $result = $this->importService->import($list, manual: true);

            return response()->json([
                'data' => [
                    'status' => 'success',
                    'records_added' => $result['added'],
                    'records_updated' => $result['updated'],
                    'records_deactivated' => $result['deactivated'],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'data' => [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    public function importLogs(): JsonResponse
    {
        $logs = SanctionImportLog::with('list')
            ->orderBy('imported_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $logs->map(fn($log) => [
                'id' => $log->id,
                'list' => [
                    'id' => $log->list?->id,
                    'name' => $log->list?->name,
                ],
                'imported_at' => $log->imported_at->toIso8601String(),
                'records_added' => $log->records_added,
                'records_updated' => $log->records_updated,
                'records_deactivated' => $log->records_deactivated,
                'status' => $log->status,
                'error_message' => $log->error_message,
                'triggered_by' => $log->triggered_by,
            ])->toArray(),
        ]);
    }

    public function storeEntry(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'list_id' => 'required|exists:sanction_lists,id',
            'entity_name' => 'required|string|max:255',
            'entity_type' => 'required|in:Individual,Entity',
            'aliases' => 'nullable|string',
            'nationality' => 'nullable|string|max:100',
            'date_of_birth' => 'nullable|date',
            'reference_number' => 'nullable|string|max:100',
            'listing_date' => 'nullable|date',
            'details' => 'nullable|array',
        ]);

        $entry = SanctionEntry::create([
            ...$validated,
            'normalized_name' => strtolower(preg_replace('/[^\p{L}\s]/u', '', $validated['entity_name'])),
            'soundex_code' => soundex($validated['entity_name']),
            'metaphone_code' => metaphone($validated['entity_name']),
            'status' => 'active',
        ]);

        return response()->json([
            'data' => [
                'id' => $entry->id,
                'entity_name' => $entry->entity_name,
            ],
        ], 201);
    }

    public function updateEntry(Request $request, int $entryId): JsonResponse
    {
        $entry = SanctionEntry::findOrFail($entryId);

        $validated = $request->validate([
            'entity_name' => 'nullable|string|max:255',
            'entity_type' => 'nullable|in:Individual,Entity',
            'aliases' => 'nullable|string',
            'nationality' => 'nullable|string|max:100',
            'date_of_birth' => 'nullable|date',
            'reference_number' => 'nullable|string|max:100',
            'listing_date' => 'nullable|date',
            'details' => 'nullable|array',
            'status' => 'nullable|in:active,inactive',
        ]);

        if (isset($validated['entity_name'])) {
            $validated['normalized_name'] = strtolower(preg_replace('/[^\p{L}\s]/u', '', $validated['entity_name']));
            $validated['soundex_code'] = soundex($validated['entity_name']);
            $validated['metaphone_code'] = metaphone($validated['entity_name']);
        }

        $entry->update($validated);

        return response()->json([
            'data' => [
                'id' => $entry->id,
                'entity_name' => $entry->entity_name,
                'status' => $entry->status,
            ],
        ]);
    }

    public function deleteEntry(int $entryId): JsonResponse
    {
        $entry = SanctionEntry::findOrFail($entryId);

        $entry->update(['status' => 'inactive']);

        return response()->json(['data' => ['message' => 'Entry deactivated']]);
    }
}
```

- [ ] **Step 5: Add routes to api.php**

```php
use App\Http\Controllers\Api\V1\ScreeningController;
use App\Http\Controllers\Api\V1\SanctionListController;

// Screening endpoints (ComplianceOfficer+)
Route::middleware(['auth', 'role:compliance'])->group(function () {
    Route::post('/screening/customer/{customer}', [ScreeningController::class, 'screen']);
    Route::get('/screening/customer/{customer}/history', [ScreeningController::class, 'history']);
    Route::get('/screening/customer/{customer}/status', [ScreeningController::class, 'status']);
    Route::post('/screening/batch', [ScreeningController::class, 'batchScreen']);
});

// Sanctions management endpoints (Admin)
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/sanctions/lists', [SanctionListController::class, 'lists']);
    Route::get('/sanctions/entries', [SanctionListController::class, 'entries']);
    Route::post('/sanctions/import/trigger/{list}', [SanctionListController::class, 'triggerImport']);
    Route::get('/sanctions/import/logs', [SanctionListController::class, 'importLogs']);
    Route::post('/sanctions/entries', [SanctionListController::class, 'storeEntry']);
    Route::put('/sanctions/entries/{entry}', [SanctionListController::class, 'updateEntry']);
    Route::delete('/sanctions/entries/{entry}', [SanctionListController::class, 'deleteEntry']);
});
```

- [ ] **Step 6: Run tests to verify**

```bash
php artisan test --filter=ScreeningApiTest
```

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/V1/ScreeningController.php app/Http/Controllers/Api/V1/SanctionListController.php routes/api.php
git commit -m "feat(api): add screening and sanctions management endpoints"
```

---

## Task 6: Wire into Transaction Flow

**Files:**
- Modify: `app/Services/TransactionService.php`

- [ ] **Step 1: Read current TransactionService to understand structure**

```bash
head -150 app/Services/TransactionService.php
```

- [ ] **Step 2: Add screening to createTransaction method**

Find where `ComplianceService` is injected and `requiresHold()` is called. Add screening call after validation:

```php
// In createTransaction method, after line ~129
// Add screening check
$screeningResult = $this->screeningService->screenCustomer($customer);

if ($screeningResult->isFlagged()) {
    $holdCheck['requires_compliance_review'] = true;
    $complianceFlags[] = 'sanction_match';
}
```

- [ ] **Step 3: Add UnifiedSanctionScreeningService to constructor**

```php
public function __construct(
    // ... existing dependencies
    protected UnifiedSanctionScreeningService $screeningService,
) {}
```

- [ ] **Step 4: Add use statement**

```php
use App\Services\UnifiedSanctionScreeningService;
```

- [ ] **Step 5: Run tests to verify**

```bash
php artisan test --filter=TransactionWorkflowTest
```

- [ ] **Step 6: Commit**

```bash
git add app/Services/TransactionService.php
git commit -m "feat(sanctions): wire UnifiedSanctionScreeningService into transaction flow"
```

---

## Task 7: Scheduled Jobs Configuration

**Files:**
- Modify: `app/Console/Kernel.php`

- [ ] **Step 1: Read current Kernel.php**

```bash
cat app/Console/Kernel.php
```

- [ ] **Step 2: Add schedule for sanctions jobs**

```php
protected function schedule(Schedule $schedule): void
{
    // UN Consolidated - Daily at 1 AM
    $schedule->job(new ImportSanctionsJob())
        ->dailyAt('01:00')
        ->withoutOverlapping()
        ->onOneServer();

    // MOHA Malaysia - Sunday at 2 AM
    $schedule->job(new ImportSanctionsJob())
        ->weeklyOn(0, '02:00')
        ->withoutOverlapping()
        ->onOneServer();

    // High risk rescreening - Daily at 4 AM
    $schedule->job(new RescreenHighRiskCustomersJob())
        ->dailyAt('04:00')
        ->withoutOverlapping()
        ->onOneServer();
}
```

- [ ] **Step 3: Create RescreenHighRiskCustomersJob**

```php
<?php
namespace App\Jobs;

use App\Models\Customer;
use App\Services\UnifiedSanctionScreeningService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RescreenHighRiskCustomersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function handle(UnifiedSanctionScreeningService $service): void
    {
        $customers = Customer::where('risk_score', '>=', 70)
            ->orWhere('sanction_hit', true)
            ->pluck('id');

        Log::info('RescreenHighRiskCustomersJob: Starting', [
            'count' => $customers->count(),
        ]);

        foreach ($customers as $customerId) {
            $customer = Customer::find($customerId);
            if ($customer) {
                try {
                    $service->screenCustomer($customer, 'Scheduled high-risk rescreening');
                } catch (\Exception $e) {
                    Log::error('RescreenHighRiskCustomersJob: Failed for customer', [
                        'customer_id' => $customerId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        Log::info('RescreenHighRiskCustomersJob: Completed');
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add app/Console/Kernel.php app/Jobs/RescreenHighRiskCustomersJob.php
git commit -m "feat(sanctions): add scheduled import and rescreening jobs"
```

---

## Task 8: Cleanup Old Services

**Files:**
- Delete: `app/Services/SanctionScreeningService.php`
- Delete: `app/Services/WatchlistApiService.php`
- Modify: `app/Services/CustomerRiskScoringService.php`

- [ ] **Step 1: Delete SanctionScreeningService**

```bash
rm app/Services/SanctionScreeningService.php
```

- [ ] **Step 2: Delete WatchlistApiService**

```bash
rm app/Services/WatchlistApiService.php
```

- [ ] **Step 3: Update CustomerRiskScoringService to use UnifiedSanctionScreeningService**

Find the `rescreenCustomer` method and update it to use the new service:

```php
public function rescreenCustomer(Customer $customer): array
{
    $response = $this->screeningService->screenCustomer($customer, 'Risk scoring rescreen');

    return [
        'action' => $response->action,
        'confidence' => $response->confidenceScore,
        'matches' => $response->matches->count(),
    ];
}
```

- [ ] **Step 4: Commit**

```bash
git rm app/Services/SanctionScreeningService.php app/Services/WatchlistApiService.php
git add app/Services/CustomerRiskScoringService.php
git commit -m "refactor(sanctions): remove deprecated screening services"
```

---

## Task 9: Final Testing

**Files:**
- Create: `tests/Feature/SanctionsImportCommandTest.php`

- [ ] **Step 1: Run all sanctions-related tests**

```bash
php artisan test --filter=sanction
```

- [ ] **Step 2: Run full test suite**

```bash
php artisan test
```

- [ ] **Step 3: Test manual import command**

```bash
php artisan sanctions:import --list=1
```

- [ ] **Step 4: Verify linting**

```bash
./vendor/bin/pint --test
```

- [ ] **Step 5: Final commit**

```bash
git add -A && git commit -m "feat(sanctions): complete sanction screening redesign"
```

---

## Spec Coverage Check

- [x] Unified screening service with consolidated matching
- [x] Auto-import from UN, MOHA, OpenSanctions sources
- [x] DOB and nationality matching
- [x] 75% threshold (flag-only policy)
- [x] Screening results persisted to `screening_results`
- [x] API endpoints for screening, history, status
- [x] Manual entry management endpoints
- [x] Import logs and history
- [x] Scheduled jobs for weekly/daily updates
- [x] Transaction flow wired to screening

---

## Execution Options

**Plan complete and saved to `docs/superpowers/plans/2026-04-16-sanction-screening-redesign.md`.**

**Two execution options:**

**1. Subagent-Driven (recommended)** - I dispatch a fresh subagent per task, review between tasks, fast iteration

**2. Inline Execution** - Execute tasks in this session using executing-plans, batch execution with checkpoints

**Which approach?**