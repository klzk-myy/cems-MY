<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

class EnsureSetupAccessibleTest extends TestCase
{
    public function test_middleware_blocks_setup_when_complete_in_all_environments(): void
    {
        $file = base_path('app/Http/Middleware/EnsureSetupAccessible.php');
        $this->assertFileExists($file);

        $content = file_get_contents($file);
        $this->assertStringContainsString('if ($setupComplete)', $content);
        $this->assertStringNotContainsString("app()->environment('production')", $content);
    }
}
