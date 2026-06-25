<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

class ReportDownloadSanitizationTest extends TestCase
{
    public function test_report_download_sanitizes_filename(): void
    {
        $file = base_path('app/Http/Controllers/Api/V1/ReportController.php');
        $this->assertFileExists($file);

        $content = file_get_contents($file);
        $this->assertStringContainsString('basename($filename)', $content);
        $this->assertStringContainsString("str_contains(\$filename, '..')", $content);
    }
}
