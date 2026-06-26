<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

class BudgetFloatTest extends TestCase
{
    public function test_budget_uses_mathservice_not_floats(): void
    {
        $file = base_path('app/Models/Budget.php');
        $this->assertFileExists($file);

        $content = file_get_contents($file);
        $this->assertStringContainsString('app(MathService::class)', $content);
        $this->assertStringNotContainsString('(float) $this->budget_amount', $content);
        $this->assertStringNotContainsString('(float) $this->actual_amount', $content);
    }
}
