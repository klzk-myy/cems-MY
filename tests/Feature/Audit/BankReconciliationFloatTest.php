<?php

namespace Tests\Feature\Audit;

use App\Models\BankReconciliation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankReconciliationFloatTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Reconciliation amounts must stay decimal-exact. getAmount() must run
     * through BCMath — a float implementation drifts on values past float53
     * precision and drops the scale-4 decimal format.
     */
    public function test_get_amount_uses_exact_decimal_math(): void
    {
        $reconciliation = new BankReconciliation([
            'debit' => '0.30',
            'credit' => '0.10',
        ]);

        $this->assertSame('0.2000', $reconciliation->getAmount());

        $large = new BankReconciliation([
            'debit' => '9007199254740.9930',
            'credit' => '0.0030',
        ]);

        $this->assertSame('9007199254740.9900', $large->getAmount());
    }
}
