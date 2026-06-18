<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NoOpTestDetectorTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function security_test_has_noassert_true_true_assertions(): void
    {
        $content = file_get_contents(base_path('tests/Feature/SecurityTest.php'));
        $this->assertStringNotContainsString('assertTrue(true)', $content);
    }

    #[Test]
    public function unit_tests_have_noassert_true_true_assertions(): void
    {
        $files = glob(base_path('tests/Unit/*.php'));
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $this->assertStringNotContainsString(
                'assertTrue(true)',
                $content,
                basename($file).' contains no-op assertTrue(true)'
            );
        }
    }
}
