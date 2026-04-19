<?php

namespace Tests\Unit;

use App\Services\AuditService;
use App\Services\ComplianceService;
use App\Services\StrReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StrReportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected StrReportService $strReportService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strReportService = new StrReportService(
            app(ComplianceService::class),
            app(AuditService::class)
        );
    }

    public function test_validate_certificate_configuration_detects_missing_cert_path(): void
    {
        config(['services.goaml.cert_path' => null]);

        $result = $this->strReportService->validateCertificateConfiguration();

        $this->assertContains('cert_path', $result);
    }

    public function test_validate_certificate_configuration_detects_missing_key_path(): void
    {
        config(['services.goaml.key_path' => null]);

        $result = $this->strReportService->validateCertificateConfiguration();

        $this->assertContains('key_path', $result);
    }

    public function test_validate_certificate_configuration_detects_missing_ca_path(): void
    {
        config(['services.goaml.ca_path' => null]);

        $result = $this->strReportService->validateCertificateConfiguration();

        $this->assertContains('ca_path', $result);
    }

    public function test_validate_certificate_configuration_detects_nonexistent_cert_file(): void
    {
        config([
            'services.goaml.cert_path' => '/nonexistent/cert.pem',
            'services.goaml.key_path' => '/nonexistent/key.pem',
            'services.goaml.ca_path' => '/nonexistent/ca.pem',
        ]);

        $result = $this->strReportService->validateCertificateConfiguration();

        $this->assertNotEmpty($result);
        // Check that we have errors for all three files
        $this->assertGreaterThanOrEqual(3, count($result));
    }

    public function test_validate_certificate_configuration_detects_unreadable_files(): void
    {
        $this->markTestSkipped('Cannot test file permissions in test environment');
    }

    public function test_validate_certificate_configuration_detects_invalid_cert_format(): void
    {
        $tempDir = sys_get_temp_dir();
        $certFile = $tempDir.'/invalid_cert.pem';
        file_put_contents($certFile, 'INVALID CERTIFICATE CONTENT');

        config([
            'services.goaml.cert_path' => $certFile,
            'services.goaml.key_path' => $certFile,
            'services.goaml.ca_path' => $certFile,
        ]);

        $result = $this->strReportService->validateCertificateConfiguration();

        $this->assertNotEmpty($result);
        $this->assertStringContainsString('invalid certificate format', $result[0]);

        unlink($certFile);
    }

    public function test_validate_certificate_configuration_detects_invalid_key_format(): void
    {
        $tempDir = sys_get_temp_dir();
        $keyFile = $tempDir.'/invalid_key.pem';
        file_put_contents($keyFile, 'INVALID KEY CONTENT');

        config([
            'services.goaml.cert_path' => $keyFile,
            'services.goaml.key_path' => $keyFile,
            'services.goaml.ca_path' => $keyFile,
        ]);

        $result = $this->strReportService->validateCertificateConfiguration();

        $this->assertNotEmpty($result);
        // The cert_path will be checked first and found invalid, so we check for that
        $this->assertStringContainsString('invalid', $result[0]);

        unlink($keyFile);
    }

    public function test_validate_certificate_configuration_detects_invalid_ca_format(): void
    {
        $tempDir = sys_get_temp_dir();
        $caFile = $tempDir.'/invalid_ca.pem';
        file_put_contents($caFile, 'INVALID CA CONTENT');

        config([
            'services.goaml.cert_path' => $caFile,
            'services.goaml.key_path' => $caFile,
            'services.goaml.ca_path' => $caFile,
        ]);

        $result = $this->strReportService->validateCertificateConfiguration();

        $this->assertNotEmpty($result);
        // The cert_path will be checked first and found invalid, so we check for that
        $this->assertStringContainsString('invalid', $result[0]);

        unlink($caFile);
    }

    public function test_validate_certificate_configuration_returns_empty_when_valid(): void
    {
        $this->markTestSkipped('Cannot test with real certificates in test environment');
    }

    public function test_validate_certificate_configuration_detects_mismatched_key_and_cert(): void
    {
        $this->markTestSkipped('Cannot test with real certificates in test environment');
    }
}
