<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\ExchangeRate;
use App\Models\User;
use App\Services\DTOs\RateOverrideResult;
use App\Services\Transaction\RateManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        $this->assertEquals('4.50000000', $result->previousRate);
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

    #[Test]
    public function override_rate_recovers_from_unique_constraint_race(): void
    {
        // Simulate a concurrent insert winning the race between the locked
        // first() lookup and create(): a `creating` hook plants the row so
        // the real insert hits the (branch_id, currency_code) unique index.
        $branch = Branch::factory()->create();
        ExchangeRate::creating(function () use ($branch) {
            DB::table('exchange_rates')->insert([
                'branch_id' => $branch->id,
                'currency_code' => 'USD',
                'rate_buy' => '4.1000',
                'rate_sell' => '4.2000',
                'rate_unit' => 1,
                'rate_inverse' => false,
                'source' => 'api',
                'fetched_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $service = app(RateManagementService::class);
        $result = $service->overrideRate('USD', '4.6000', '4.7000', $this->createManager(), null, $branch->id);

        // The retry re-fetches the race-winning row and reports the call as a
        // create (null previous rate), without overwriting its values.
        $this->assertTrue($result->success);
        $this->assertNull($result->previousRate);
        $this->assertSame('Rate for USD created successfully', $result->message);

        $rows = ExchangeRate::where('currency_code', 'USD')->get();
        $this->assertCount(1, $rows);
        $this->assertSame('4.10000000', $rows->first()->rate_buy);
    }
}
