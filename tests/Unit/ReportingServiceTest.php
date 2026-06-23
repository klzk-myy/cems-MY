<?php

namespace Tests\Unit;

use App\Services\Reporting\ReportingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportingServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function generate_form_lmca_uses_configured_license_number(): void
    {
        config(['cems.license_number' => 'MSB-TEST-12345']);

        $service = $this->app->make(ReportingService::class);
        $result = $service->generateFormLMCA(now()->format('Y-m'));

        $this->assertEquals('MSB-TEST-12345', $result['license_number']);
    }
}
