<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

class ComplianceCaseNumberTest extends TestCase
{
    public function test_generate_case_number_uses_lock_for_update(): void
    {
        $file = base_path('app/Models/Compliance/ComplianceCase.php');
        $this->assertFileExists($file);

        $content = file_get_contents($file);
        $this->assertStringContainsString('lockForUpdate()', $content, 'Should use lockForUpdate to prevent race');
        $this->assertStringContainsString('while (true)', $content, 'Should have retry loop');
        $this->assertStringContainsString('$maxRetries', $content, 'Should have max retries');
    }
}
