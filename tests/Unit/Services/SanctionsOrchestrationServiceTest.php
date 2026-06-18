<?php

namespace Tests\Unit\Services;

use App\Enums\SanctionListType;
use App\Models\SanctionList;
use App\Services\Compliance\SanctionsDownloadService;
use App\Services\Compliance\SanctionsImportService;
use App\Services\Compliance\SanctionsOrchestrationService;
use App\Services\System\MathService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SanctionsOrchestrationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SanctionsOrchestrationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SanctionsOrchestrationService(
            new SanctionsDownloadService,
            new SanctionsImportService(new MathService(2))
        );
    }

    #[Test]
    public function sync_sanctions_list_downloads_and_imports(): void
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
            'source_format' => 'JSON',
            'list_type' => SanctionListType::UNSCR,
        ]);

        $result = $this->service->syncSanctionsList($list, true);

        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('created', $result);
        $this->assertArrayHasKey('updated', $result);
        $this->assertArrayHasKey('deactivated', $result);
    }

    #[Test]
    public function sync_sanctions_list_returns_error_on_download_failure(): void
    {
        Http::fake([
            '*' => Http::response('Server Error', 500),
        ]);

        $list = SanctionList::factory()->create([
            'source_url' => 'https://api.opensanctions.org/test',
            'slug' => 'test-sanctions-list',
            'is_active' => true,
            'source_format' => 'JSON',
        ]);

        $result = $this->service->syncSanctionsList($list, true);

        $this->assertFalse($result['success']);
        $this->assertNotNull($result['error']);
    }

    #[Test]
    public function sync_sanctions_list_handles_invalid_json(): void
    {
        Http::fake([
            '*' => Http::response('not valid json {', 200),
        ]);

        $list = SanctionList::factory()->create([
            'source_url' => 'https://api.opensanctions.org/test',
            'slug' => 'test-sanctions-list',
            'is_active' => true,
            'source_format' => 'JSON',
        ]);

        $result = $this->service->syncSanctionsList($list, true);

        $this->assertFalse($result['success']);
        $this->assertEquals('Downloaded content is not valid JSON', $result['error']);
    }

    #[Test]
    public function sync_sanctions_list_calls_import_service(): void
    {
        Http::fake([
            '*' => Http::response([
                'results' => [
                    ['id' => 'us-001', 'name' => 'John Doe', 'entity_type' => 'Person'],
                ],
            ], 200),
        ]);

        $list = SanctionList::factory()->create([
            'source_url' => 'https://api.opensanctions.org/test',
            'slug' => 'test-sync-list',
            'is_active' => true,
            'source_format' => 'JSON',
        ]);

        $mockImportService = $this->createMock(SanctionsImportService::class);

        $mockImportService->expects($this->once())
            ->method('importWithData')
            ->willReturn([
                'success' => true,
                'created' => 1,
                'updated' => 0,
                'deactivated' => 0,
            ]);

        $service = new SanctionsOrchestrationService(
            new SanctionsDownloadService,
            $mockImportService
        );

        $result = $service->syncSanctionsList($list, true);

        $this->assertTrue($result['success']);
    }
}
