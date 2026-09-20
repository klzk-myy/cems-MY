<?php

namespace Tests\Unit\Services\Compliance;

use App\Enums\SanctionSourceFormat;
use App\Services\Compliance\SanctionsDownloadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SanctionsDownloadServiceTest extends TestCase
{
    use RefreshDatabase;

    private SanctionsDownloadService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SanctionsDownloadService;
    }

    #[Test]
    public function download_returns_error_on_http_failure(): void
    {
        Http::fake([
            '*' => Http::response('', 500),
        ]);

        $result = $this->service->download('https://un.org/list.xml', 'list.xml', SanctionSourceFormat::Xml, 1);

        $this->assertFalse($result['success']);
        $this->assertNotNull($result['error']);
    }

    #[Test]
    public function download_returns_success_on_successful_http_response(): void
    {
        Http::fake([
            '*' => Http::response('<?xml version="1.0"?><root/>', 200),
        ]);

        $result = $this->service->download('https://un.org/list.xml', 'list.xml', SanctionSourceFormat::Xml, 1);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('list.xml', $result['filepath']);
    }
}
