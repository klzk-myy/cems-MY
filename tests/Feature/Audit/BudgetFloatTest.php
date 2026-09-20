<?php

namespace Tests\Feature\Audit;

use App\Models\Budget;
use Tests\TestCase;

class BudgetFloatTest extends TestCase
{
    public function test_budget_variance_preserves_decimal_precision(): void
    {
        $budget = new Budget([
            'budget_myr' => '0.30',
            'actual_myr' => '0.10',
        ]);

        $this->assertSame('0.2000', $budget->getVariance());
    }

    public function test_budget_variance_survives_float53_magnitude(): void
    {
        $budget = new Budget([
            'budget_myr' => '9007199254740.9930',
            'actual_myr' => '0.0030',
        ]);

        $this->assertSame('9007199254740.9900', $budget->getVariance());
        $this->assertFalse($budget->isOverBudget());
    }

    public function test_budget_over_budget_detection_uses_decimal_compare(): void
    {
        $budget = new Budget([
            'budget_myr' => '100.0000',
            'actual_myr' => '100.0001',
        ]);

        $this->assertSame('-0.0001', $budget->getVariance());
        $this->assertTrue($budget->isOverBudget());
    }
}
