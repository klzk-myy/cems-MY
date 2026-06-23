<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\ExchangeRate;
use App\Models\User;
use App\Services\DTOs\RateOverrideResult;
use App\Services\Transaction\RateManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RateOverrideResultDtoTest extends TestCase
{
    use RefreshDatabase;

    protected function createManager(): User
    {
        return User::factory()->create([
            'role' => UserRole::Manager,
        ]);
    }

    #[Test]
    public function override_rate_returns_rate_override_result_dto()
    {
        ExchangeRate::factory()->create([
            'currency_code' => 'USD',
            'rate_buy' => '4.5000',
            'rate_sell' => '4.6000',
            'source' => 'api',
            'fetched_at' => now(),
        ]);

        $service = app(RateManagementService::class);
        $result = $service->overrideRate('USD', '4.6000', '4.7000', $this->createManager());

        $this->assertInstanceOf(RateOverrideResult::class, $result);
    }

    #[Test]
    public function override_rate_dto_success_contains_previous_and_new_rates()
    {
        ExchangeRate::factory()->create([
            'currency_code' => 'USD',
            'rate_buy' => '4.5000',
            'rate_sell' => '4.6000',
            'source' => 'api',
            'fetched_at' => now(),
        ]);

        $service = app(RateManagementService::class);
        $result = $service->overrideRate('USD', '4.6000', '4.7000', $this->createManager());

        $this->assertTrue($result->success);
        $this->assertEquals('4.5000', $result->previousRate);
        $this->assertEquals('4.6000', $result->newRate);
    }

    #[Test]
    public function override_rate_dto_insufficient_permissions()
    {
        $staff = User::factory()->create([
            'role' => UserRole::Teller,
        ]);

        $service = app(RateManagementService::class);
        $result = $service->overrideRate('USD', '4.6000', '4.7000', $staff);

        $this->assertInstanceOf(RateOverrideResult::class, $result);
        $this->assertFalse($result->success);
    }

    #[Test]
    public function override_rate_dto_new_exchange_rate_returns_null_previous_rate()
    {
        $service = app(RateManagementService::class);
        $result = $service->overrideRate('EUR', '5.0000', '5.1000', $this->createManager());

        $this->assertInstanceOf(RateOverrideResult::class, $result);
        $this->assertTrue($result->success);
        $this->assertNull($result->previousRate);
        $this->assertEquals('5.0000', $result->newRate);
    }
}
