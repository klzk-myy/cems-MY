<?php

namespace Tests\Unit;

use App\Models\ChartOfAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChartOfAccountTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function unused_methods_removed()
    {
        $account = ChartOfAccount::factory()->create(['account_type' => 'Asset']);

        $this->assertFalse(method_exists($account, 'isAsset'));
        $this->assertFalse(method_exists($account, 'isLiability'));
        $this->assertFalse(method_exists($account, 'isEquity'));
        $this->assertFalse(method_exists($account, 'isRevenue'));
        $this->assertFalse(method_exists($account, 'isExpense'));
    }
}
