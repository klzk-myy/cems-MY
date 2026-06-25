<?php

namespace Tests\Unit\Risk;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PatternRiskServiceTest extends TestCase
{
    #[Test]
    public function pattern_risk_service_uses_correct_math_property(): void
    {
        $file = base_path('app/Services/Risk/PatternRiskService.php');
        $this->assertFileExists($file);

        $content = file_get_contents($file);
        $this->assertStringContainsString('$this->mathService->compare', $content, 'Should use mathService->compare');
        $this->assertStringNotContainsString('$this->math->compare', $content, 'Should not use $this->math->compare');
    }
}
