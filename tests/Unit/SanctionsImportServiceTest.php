<?php

namespace Tests\Unit;

use App\Enums\EntityType;
use App\Enums\SanctionStatus;
use App\Models\Compliance\SanctionEntry;
use App\Models\Compliance\SanctionList;
use App\Services\Compliance\SanctionsDownloadService;
use App\Services\Compliance\SanctionsImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SanctionsImportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SanctionsImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SanctionsImportService::class);
    }

    #[Test]
    public function import_creates_entries_from_json(): void
    {
        Http::fake([
            'https://api.opensanctions.org/*' => Http::response([
                'results' => [
                    [
                        'id' => 'us-001',
                        'name' => ['John Doe', 'Johnny Doe'],
                        'entity_type' => 'Person',
                        'nationality' => 'US',
                        'birth_date' => '1990-01-15',
                    ],
                    [
                        'id' => 'us-002',
                        'name' => 'Acme Corporation',
                        'entity_type' => 'Organization',
                        'nationality' => 'GB',
                    ],
                ],
            ], 200),
        ]);

        $list = SanctionList::factory()->create([
            'source_url' => 'https://api.opensanctions.org/test',
            'slug' => 'test-sanctions-list',
            'is_active' => true,
        ]);

        $result = $this->service->import($list, true);

        $this->assertEquals(2, $result['created']);
        $this->assertEquals(0, $result['updated']);
        $this->assertEquals(0, $result['deactivated']);
        $this->assertEquals(0, $result['errors']);

        $entries = SanctionEntry::where('list_id', $list->id)->get();

        $this->assertCount(2, $entries);

        $johnDoe = $entries->firstWhere('reference_number', 'us-001');
        $this->assertNotNull($johnDoe);
        $this->assertEquals('John Doe', $johnDoe->entity_name);
        $this->assertEquals(EntityType::Individual, $johnDoe->entity_type);
        $this->assertEquals('US', $johnDoe->nationality);
        $this->assertEquals('1990-01-15', $johnDoe->date_of_birth->format('Y-m-d'));
        $this->assertEquals('john doe', $johnDoe->normalized_name);
        $this->assertEquals(['Johnny Doe'], $johnDoe->aliases);

        $acme = $entries->firstWhere('reference_number', 'us-002');
        $this->assertNotNull($acme);
        $this->assertEquals('Acme Corporation', $acme->entity_name);
        $this->assertEquals(EntityType::Organization, $acme->entity_type);
        $this->assertEquals('GB', $acme->nationality);
        $this->assertEquals('acme corporation', $acme->normalized_name);
    }

    #[Test]
    public function import_updates_existing_entries(): void
    {
        Http::fake([
            'https://api.opensanctions.org/*' => Http::response([
                'results' => [
                    [
                        'id' => 'us-001',
                        'name' => 'John Doe Updated',
                        'entity_type' => 'Person',
                        'nationality' => 'Canada',
                    ],
                    [
                        'id' => 'us-002',
                        'name' => 'New Corporation',
                        'entity_type' => 'Organization',
                    ],
                ],
            ], 200),
        ]);

        $list = SanctionList::factory()->create([
            'source_url' => 'https://api.opensanctions.org/test',
            'slug' => 'test-sanctions-list',
            'is_active' => true,
        ]);

        $existingEntry = SanctionEntry::factory()->create([
            'list_id' => $list->id,
            'reference_number' => 'us-001',
            'entity_name' => 'John Doe Original',
            'normalized_name' => 'john doe original',
            'entity_type' => 'Individual',
            'nationality' => 'US',
            'status' => 'active',
        ]);

        $result = $this->service->import($list, true);

        $this->assertEquals(1, $result['created']);
        $this->assertEquals(1, $result['updated']);
        $this->assertEquals(0, $result['deactivated']);

        $existingEntry->refresh();
        $this->assertEquals('John Doe Updated', $existingEntry->entity_name);
        $this->assertEquals('john doe updated', $existingEntry->normalized_name);
        $this->assertEquals('Canada', $existingEntry->nationality);
    }

    #[Test]
    public function import_deactivates_missing_entries(): void
    {
        Http::fake([
            'https://api.opensanctions.org/*' => Http::response([
                'results' => [
                    [
                        'id' => 'us-001',
                        'name' => 'John Doe',
                        'entity_type' => 'Person',
                    ],
                ],
            ], 200),
        ]);

        $list = SanctionList::factory()->create([
            'source_url' => 'https://api.opensanctions.org/test',
            'slug' => 'test-sanctions-list',
            'is_active' => true,
        ]);

        SanctionEntry::factory()->create([
            'list_id' => $list->id,
            'reference_number' => 'us-001',
            'entity_name' => 'John Doe',
            'status' => 'active',
        ]);

        SanctionEntry::factory()->create([
            'list_id' => $list->id,
            'reference_number' => 'us-999',
            'entity_name' => 'To Be Deactivated',
            'status' => 'active',
        ]);

        $result = $this->service->import($list, true);

        $this->assertEquals(0, $result['created']);
        $this->assertEquals(1, $result['updated']);
        $this->assertEquals(1, $result['deactivated']);

        $deactivated = SanctionEntry::where('reference_number', 'us-999')->first();
        $this->assertEquals(SanctionStatus::Inactive, $deactivated->status);

        $stillActive = SanctionEntry::where('reference_number', 'us-001')->first();
        $this->assertEquals(SanctionStatus::Active, $stillActive->status);
    }

    #[Test]
    public function parse_open_sanctions_entry_handles_array_names(): void
    {
        $list = SanctionList::factory()->create(['slug' => 'test-parsing-list']);

        $item = [
            'id' => 'test-001',
            'name' => ['Primary Name', 'Alias One', 'Alias Two'],
            'entity_type' => 'Person',
            'aliases' => ['Old Alias'],
            'nationality' => 'US',
            'birth_date' => '1985-05-20',
        ];

        $result = $this->service->parseOpenSanctionsEntry($item, $list);

        $this->assertNotNull($result);
        $this->assertEquals('Primary Name', $result['entity_name']);
        $this->assertEquals('primary name', $result['normalized_name']);
        $this->assertEquals(EntityType::Individual, $result['entity_type']);
        $this->assertEquals('US', $result['nationality']);
        $this->assertEquals('1985-05-20', $result['date_of_birth']);
    }

    #[Test]
    public function parse_open_sanctions_entry_returns_null_for_missing_name(): void
    {
        $list = SanctionList::factory()->create(['slug' => 'test-missing-name']);

        $item = [
            'id' => 'test-001',
            'entity_type' => 'Person',
        ];

        $result = $this->service->parseOpenSanctionsEntry($item, $list);

        $this->assertNull($result);
    }

    #[Test]
    public function normalize_name(): void
    {
        $this->assertEquals('john doe', $this->service->normalizeName('John Doe'));
        $this->assertEquals('john doe', $this->service->normalizeName('  John   Doe  '));
        $this->assertEquals('john doe', $this->service->normalizeName('JOHN DOE'));
        $this->assertEquals('john omalley', $this->service->normalizeName("John O'Malley"));
        $this->assertEquals('john doe smith', $this->service->normalizeName('John Doe-Smith'));
    }

    #[Test]
    public function map_entity_type(): void
    {
        $this->assertEquals(EntityType::Individual, $this->service->mapEntityType('Person'));
        $this->assertEquals(EntityType::Individual, $this->service->mapEntityType('Individual'));
        $this->assertEquals(EntityType::Individual, $this->service->mapEntityType('natural person'));
        $this->assertEquals(EntityType::Organization, $this->service->mapEntityType('Organization'));
        $this->assertEquals(EntityType::Organization, $this->service->mapEntityType('Entity'));
        $this->assertEquals(EntityType::Vessel, $this->service->mapEntityType('Vessel'));
        $this->assertEquals(EntityType::Individual, $this->service->mapEntityType(null));
        $this->assertEquals(EntityType::Individual, $this->service->mapEntityType(''));
    }

    #[Test]
    public function parse_date(): void
    {
        $this->assertEquals('1990-01-15', $this->service->parseDate('1990-01-15'));
        $this->assertEquals('1990-01-01', $this->service->parseDate('1990'));
        $this->assertEquals('1990-01-15', $this->service->parseDate('1990/01/15'));
        $this->assertEquals('1990-01-15', $this->service->parseDate('January 15, 1990'));
        $this->assertNull($this->service->parseDate(null));
        $this->assertNull($this->service->parseDate(''));
    }

    #[Test]
    public function fetch_source_retries_on_failure(): void
    {
        config(['sanctions.download.retry_delay' => 0]);

        Http::fake([
            'https://api.opensanctions.org/*' => Http::sequence()
                ->push('Server Error', 500)
                ->push('Server Error', 500)
                ->push([
                    'results' => [
                        ['id' => 'test-001', 'name' => 'Test Entry'],
                    ],
                ], 200),
        ]);

        $list = SanctionList::factory()->create([
            'source_url' => 'https://api.opensanctions.org/test',
            'slug' => 'test-retry-list',
        ]);

        $result = $this->service->import($list, true);

        $this->assertEquals(1, $result['created']);
    }

    #[Test]
    public function import_deletes_temp_file_after_consuming_stream(): void
    {
        $base = sys_get_temp_dir().'/cems-import-temp-'.uniqid();
        $tempDir = $base.'/temp';
        $archiveDir = $base.'/archive';
        mkdir($tempDir, 0755, true);
        mkdir($archiveDir, 0755, true);
        config([
            'sanctions.download.temp_directory' => $tempDir,
            'sanctions.download.archive_directory' => $archiveDir,
        ]);

        // SanctionsDownloadService resolves temp_directory at construction —
        // build a fresh service so it picks up the overridden config.
        $service = app(SanctionsImportService::class);

        Http::fake([
            'https://api.opensanctions.org/*' => Http::response([
                'results' => [
                    ['id' => 'us-001', 'name' => 'John Doe'],
                ],
            ], 200),
        ]);

        $list = SanctionList::factory()->create([
            'source_url' => 'https://api.opensanctions.org/test',
            'slug' => 'test-temp-cleanup',
            'is_active' => true,
        ]);

        $service->import($list, true);

        // The downloaded temp file is removed once the stream is consumed;
        // the archive copy remains.
        $this->assertCount(0, glob($tempDir.'/*') ?: []);
        $this->assertCount(1, glob($archiveDir.'/*') ?: []);

        foreach (glob($archiveDir.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($tempDir);
        rmdir($archiveDir);
        rmdir($base);
    }

    #[Test]
    public function import_applies_delta_ops_and_stores_dataset_version(): void
    {
        config(['sanctions.download.retry_delay' => 0]);

        Http::fake([
            'https://api.opensanctions.org/test/index.json' => Http::response([
                'version' => '20260912000000-b',
                'delta_url' => 'https://api.opensanctions.org/test/delta.json',
            ], 200),
            'https://api.opensanctions.org/test/delta.json' => Http::response([
                'versions' => [
                    '20260911000000-a' => 'https://api.opensanctions.org/test/delta_a.json',
                    '20260912000000-b' => 'https://api.opensanctions.org/test/delta_b.json',
                ],
            ], 200),
            'https://api.opensanctions.org/test/delta_b.json' => Http::response(
                '{"op":"ADD","entity":{"id":"d-1","caption":"Delta Person","schema":"Person","properties":{"name":["Delta Person"],"birthDate":["1990-01-01"]}}}'."\n".
                '{"op":"DEL","entity":{"id":"old-1"}}',
                200
            ),
        ]);

        $list = SanctionList::factory()->create([
            'source_url' => 'https://api.opensanctions.org/test/targets.nested.json',
            'slug' => 'test-delta-list',
            'last_dataset_version' => '20260911000000-a',
            'is_active' => true,
        ]);

        SanctionEntry::factory()->create([
            'list_id' => $list->id,
            'reference_number' => 'old-1',
            'entity_name' => 'Old Entry',
            'status' => 'active',
        ]);

        $result = $this->service->import($list, true);

        $this->assertEquals(1, $result['created']);
        $this->assertEquals(1, $result['deactivated']);

        $this->assertEquals('Delta Person', SanctionEntry::where('reference_number', 'd-1')->value('entity_name'));
        $this->assertEquals(SanctionStatus::Inactive, SanctionEntry::where('reference_number', 'old-1')->value('status'));

        $list->refresh();
        $this->assertEquals('20260912000000-b', $list->last_dataset_version);
        $this->assertEquals('success', $list->update_status->value);
    }

    #[Test]
    public function import_skips_sync_when_dataset_version_unchanged(): void
    {
        Http::fake([
            'https://api.opensanctions.org/test/index.json' => Http::response([
                'version' => '20260912000000-b',
                'delta_url' => 'https://api.opensanctions.org/test/delta.json',
            ], 200),
        ]);

        $list = SanctionList::factory()->create([
            'source_url' => 'https://api.opensanctions.org/test/targets.nested.json',
            'slug' => 'test-unchanged-list',
            'last_dataset_version' => '20260912000000-b',
            'is_active' => true,
        ]);

        $result = $this->service->import($list, true);

        $this->assertEquals(0, $result['created']);
        $this->assertEquals(0, $result['updated']);
        $this->assertEquals(0, $result['deactivated']);

        // No download of the full export and no delta manifest fetch happened.
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'targets.nested.json'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'delta.json'));

        $list->refresh();
        $this->assertEquals('success', $list->update_status->value);
        $this->assertDatabaseHas('sanction_import_logs', [
            'list_id' => $list->id,
            'status' => 'success',
        ]);
    }

    #[Test]
    public function import_falls_back_to_full_sync_when_version_missing_from_manifest(): void
    {
        config(['sanctions.download.retry_delay' => 0]);

        Http::fake([
            'https://api.opensanctions.org/test/index.json' => Http::response([
                'version' => '20260912000000-b',
                'delta_url' => 'https://api.opensanctions.org/test/delta.json',
            ], 200),
            'https://api.opensanctions.org/test/delta.json' => Http::response([
                'versions' => [
                    '20260911000000-a' => 'https://api.opensanctions.org/test/delta_a.json',
                    '20260912000000-b' => 'https://api.opensanctions.org/test/delta_b.json',
                ],
            ], 200),
            'https://api.opensanctions.org/test/targets.nested.json' => Http::response([
                'results' => [
                    ['id' => 'full-1', 'name' => 'Full Sync Entry'],
                ],
            ], 200),
        ]);

        // Stored baseline aged out of the manifest window → full sync.
        $list = SanctionList::factory()->create([
            'source_url' => 'https://api.opensanctions.org/test/targets.nested.json',
            'slug' => 'test-stale-list',
            'last_dataset_version' => '20260901000000-zzz',
            'is_active' => true,
        ]);

        $result = $this->service->import($list, true);

        $this->assertEquals(1, $result['created']);
        $this->assertDatabaseHas('sanction_entries', [
            'list_id' => $list->id,
            'reference_number' => 'full-1',
        ]);

        $list->refresh();
        $this->assertEquals('20260912000000-b', $list->last_dataset_version);
    }

    #[Test]
    public function import_handles_empty_results(): void
    {
        Http::fake([
            'https://api.opensanctions.org/*' => Http::response(['results' => []], 200),
        ]);

        $list = SanctionList::factory()->create([
            'source_url' => 'https://api.opensanctions.org/test',
            'slug' => 'test-sanctions-list',
            'is_active' => true,
        ]);

        $result = $this->service->import($list, true);

        $this->assertEquals(0, $result['created']);
        $this->assertEquals(0, $result['updated']);
        $this->assertEquals(0, $result['deactivated']);
    }
}
