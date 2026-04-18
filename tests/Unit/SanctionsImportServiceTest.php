<?php

namespace Tests\Unit;

use App\Models\SanctionEntry;
use App\Models\SanctionList;
use App\Services\MathService;
use App\Services\SanctionsImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SanctionsImportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SanctionsImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SanctionsImportService(new MathService(2));
    }

    public function test_import_creates_entries_from_json(): void
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
        $this->assertEquals('Individual', $johnDoe->entity_type);
        $this->assertEquals('US', $johnDoe->nationality);
        $this->assertEquals('1990-01-15', $johnDoe->date_of_birth->format('Y-m-d'));
        $this->assertEquals('john doe', $johnDoe->normalized_name);
        $this->assertEquals(['Johnny Doe'], $johnDoe->aliases);

        $acme = $entries->firstWhere('reference_number', 'us-002');
        $this->assertNotNull($acme);
        $this->assertEquals('Acme Corporation', $acme->entity_name);
        $this->assertEquals('Entity', $acme->entity_type);
        $this->assertEquals('GB', $acme->nationality);
        $this->assertEquals('acme corporation', $acme->normalized_name);
    }

    public function test_import_updates_existing_entries(): void
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

    public function test_import_deactivates_missing_entries(): void
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
        $this->assertEquals('inactive', $deactivated->status);

        $stillActive = SanctionEntry::where('reference_number', 'us-001')->first();
        $this->assertEquals('active', $stillActive->status);
    }

    public function test_parse_open_sanctions_entry_handles_array_names(): void
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
        $this->assertEquals('Individual', $result['entity_type']);
        $this->assertEquals('US', $result['nationality']);
        $this->assertEquals('1985-05-20', $result['date_of_birth']);
    }

    public function test_parse_open_sanctions_entry_returns_null_for_missing_name(): void
    {
        $list = SanctionList::factory()->create(['slug' => 'test-missing-name']);

        $item = [
            'id' => 'test-001',
            'entity_type' => 'Person',
        ];

        $result = $this->service->parseOpenSanctionsEntry($item, $list);

        $this->assertNull($result);
    }

    public function test_normalize_name(): void
    {
        $this->assertEquals('john doe', $this->service->normalizeName('John Doe'));
        $this->assertEquals('john doe', $this->service->normalizeName('  John   Doe  '));
        $this->assertEquals('john doe', $this->service->normalizeName('JOHN DOE'));
        $this->assertEquals("john o'malley", $this->service->normalizeName("John O'Malley"));
        $this->assertEquals('john doe-smith', $this->service->normalizeName('John Doe-Smith'));
    }

    public function test_map_entity_type(): void
    {
        $this->assertEquals('Individual', $this->service->mapEntityType('Person'));
        $this->assertEquals('Individual', $this->service->mapEntityType('Individual'));
        $this->assertEquals('Individual', $this->service->mapEntityType('natural person'));
        $this->assertEquals('Entity', $this->service->mapEntityType('Organization'));
        $this->assertEquals('Entity', $this->service->mapEntityType('Entity'));
        $this->assertEquals('Entity', $this->service->mapEntityType('Vessel'));
        $this->assertEquals('Individual', $this->service->mapEntityType(null));
        $this->assertEquals('Individual', $this->service->mapEntityType(''));
    }

    public function test_parse_date(): void
    {
        $this->assertEquals('1990-01-15', $this->service->parseDate('1990-01-15'));
        $this->assertEquals('1990-01-01', $this->service->parseDate('1990'));
        $this->assertEquals('1990-01-15', $this->service->parseDate('1990/01/15'));
        $this->assertEquals('1990-01-15', $this->service->parseDate('January 15, 1990'));
        $this->assertNull($this->service->parseDate(null));
        $this->assertNull($this->service->parseDate(''));
    }

    public function test_fetch_source_retries_on_failure(): void
    {
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

    public function test_import_handles_empty_results(): void
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
