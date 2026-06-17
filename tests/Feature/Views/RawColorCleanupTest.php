<?php

namespace Tests\Feature\Views;

use Tests\TestCase;

class RawColorCleanupTest extends TestCase
{
    private function assertViewHasNoRawColor(string $view, array $rawColors): void
    {
        $path = resource_path('views/'.str_replace('.', '/', $view).'.blade.php');
        $content = file_get_contents($path);

        foreach ($rawColors as $color) {
            $this->assertStringNotContainsString($color, $content, "View {$view} should not contain {$color}.");
        }
    }

    public function test_trusted_devices_has_no_raw_blue(): void
    {
        $this->assertViewHasNoRawColor('mfa.trusted-devices', ['bg-blue-100', 'text-blue-600']);
    }

    public function test_mfa_verify_has_no_raw_blue(): void
    {
        $this->assertViewHasNoRawColor('pages.mfa.verify', ['text-blue-600']);
    }

    public function test_sanctions_show_has_no_raw_blue(): void
    {
        $this->assertViewHasNoRawColor('compliance.sanctions.show', ['text-blue-600', 'hover:text-blue-800']);
    }

    public function test_sanctions_index_has_no_raw_blue(): void
    {
        $this->assertViewHasNoRawColor('compliance.sanctions.index', ['text-blue-600', 'hover:text-blue-800']);
    }

    public function test_compliance_summary_has_no_raw_blue(): void
    {
        $this->assertViewHasNoRawColor('reports.compliance-summary', ['text-blue-600']);
    }

    public function test_test_results_statistics_has_no_raw_status_colors(): void
    {
        $this->assertViewHasNoRawColor('test-results.statistics', ['bg-green-500', 'bg-red-500', 'bg-yellow-500']);
    }
}
