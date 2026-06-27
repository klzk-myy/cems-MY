<?php

namespace Tests\Feature\Audit;

use App\Enums\AccountingPeriodStatus;
use App\Models\AccountingPeriod;
use App\Models\User;
use App\Services\Accounting\PeriodCloseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_period_close_uses_enum_value(): void
    {
        $period = AccountingPeriod::factory()->create([
            'status' => AccountingPeriodStatus::Open,
        ]);
        $user = User::factory()->create();

        $service = app(PeriodCloseService::class);
        $result = $service->closePeriod($period, $user->id);

        $this->assertTrue($result['success']);
        $this->assertSame(AccountingPeriodStatus::Closed->value, $period->fresh()->status->value);
    }
}
