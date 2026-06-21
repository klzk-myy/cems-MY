<?php

namespace Tests\Unit\Models\Relationships;

use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingPeriodRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_belongs_to_fiscal_year(): void
    {
        $fiscalYear = FiscalYear::factory()->create();
        $period = AccountingPeriod::factory()->create(['fiscal_year_id' => $fiscalYear->id]);

        $this->assertTrue($period->fiscalYear->is($fiscalYear));
    }
}
