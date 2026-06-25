<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

class BankReconciliationFloatTest extends TestCase
{
    public function test_bank_reconciliation_uses_mathservice_not_floats(): void
    {
        $file = base_path('app/Models/BankReconciliation.php');
        $this->assertFileExists($file);

        $content = file_get_contents($file);
        $this->assertStringContainsString('app(\\App\\Services\\System\\MathService::class)', $content);
        $this->assertStringNotContainsString('(float) $this->debit', $content);
        $this->assertStringNotContainsString('(float) $this->credit', $content);
    }
}
