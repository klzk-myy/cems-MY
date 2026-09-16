<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\ExchangeRate;
use App\Models\User;
use App\Services\Transaction\RateManagementService;
use Illuminate\Cache\ArrayStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RateManagementServiceCacheTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function get_rate_for_currency_uses_cache()
    {
        ExchangeRate::factory()->create([
            'currency_code' => 'USD',
            'rate_buy' => '4.5000',
            'rate_sell' => '4.6000',
            'source' => 'api',
            'fetched_at' => now(),
        ]);

        Cache::shouldReceive('remember')
            ->once()
            ->andReturn(ExchangeRate::first());

        $service = app(RateManagementService::class);
        $rate = $service->getRateCard('USD');

        $this->assertInstanceOf(ExchangeRate::class, $rate);
        $this->assertEquals('4.50000000', $rate->rate_buy);
    }

    #[Test]
    public function override_rate_invalidates_cache()
    {
        ExchangeRate::factory()->create([
            'currency_code' => 'USD',
            'rate_buy' => '4.5000',
            'rate_sell' => '4.6000',
            'source' => 'api',
            'fetched_at' => now(),
        ]);

        // Expect cache forget for key 'rate:USD'. A company-wide write also
        // forgets every branch-scoped key plus the transaction-form table
        // key — both covered by the expectations below. The
        // role-permission matrix lookup also goes through Cache::remember —
        // pass it through.
        Cache::shouldReceive('remember')
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());
        Cache::shouldReceive('forget')
            ->once()
            ->with('rate:USD');
        Cache::shouldReceive('forget')
            ->with(\Mockery::pattern('/^rate:USD:branch:\d+$/'));
        Cache::shouldReceive('forget')
            ->with('exchange_rates_for_transactions');
        // Rate invalidation now also flushes the 'rates' tag; the array test
        // store is taggable, so mock the tag path too.
        Cache::shouldReceive('getStore')->zeroOrMoreTimes()->andReturn(new ArrayStore);
        Cache::shouldReceive('tags')->zeroOrMoreTimes()->andReturnSelf();
        Cache::shouldReceive('flush')->zeroOrMoreTimes();

        $service = app(RateManagementService::class);
        // Create a manager user to authorize override
        $manager = User::factory()->create([
            'role' => UserRole::Manager,
        ]);

        $service->overrideRate('USD', '4.6000', '4.7000', $manager);
    }

    #[Test]
    public function rate_override_is_atomic()
    {
        ExchangeRate::factory()->create([
            'currency_code' => 'USD',
            'rate_buy' => '4.5000',
            'rate_sell' => '4.6000',
            'source' => 'api',
            'fetched_at' => now(),
        ]);

        $manager = User::factory()->create([
            'role' => UserRole::Manager,
        ]);

        $service = app(RateManagementService::class);

        // First override should succeed
        $result1 = $service->overrideRate('USD', '4.6000', '4.7000', $manager, 'First override');
        $this->assertTrue($result1->success);
        $this->assertEquals('4.50000000', $result1->previousRate);
        $this->assertEquals('4.6000', $result1->newRate);

        // Second override should also succeed (sequential, not concurrent)
        $result2 = $service->overrideRate('USD', '4.7000', '4.8000', $manager, 'Second override');
        $this->assertTrue($result2->success);
        $this->assertEquals('4.60000000', $result2->previousRate);
        $this->assertEquals('4.7000', $result2->newRate);

        // Verify final rate is the second override
        $rate = ExchangeRate::where('currency_code', 'USD')->first();
        $this->assertEquals('4.70000000', $rate->rate_buy);
        $this->assertEquals('4.80000000', $rate->rate_sell);
    }

    #[Test]
    public function company_wide_override_also_invalidates_branch_scoped_rate_cache(): void
    {
        $branch = Branch::factory()->create();

        ExchangeRate::factory()->create([
            'currency_code' => 'USD',
            'branch_id' => $branch->id,
            'rate_buy' => '4.7000',
            'rate_sell' => '4.8000',
            'fetched_at' => now(),
        ]);
        ExchangeRate::factory()->create([
            'currency_code' => 'USD',
            'rate_buy' => '4.5000',
            'rate_sell' => '4.6000',
            'fetched_at' => now(),
        ]);

        $manager = User::factory()->create([
            'role' => UserRole::Manager,
        ]);

        // A branch reader resolves to the company card when its branch has no
        // override, so a company-wide write must forget the branch key too —
        // otherwise the branch serves the pre-write rate until the TTL.
        Cache::shouldReceive('remember')
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());
        Cache::shouldReceive('forget')->with('rate:USD')->once();
        Cache::shouldReceive('forget')->with("rate:USD:branch:{$branch->id}")->once();
        // Other seeded branches' keys and the transaction-form table key are
        // forgotten too — a company write enumerates every branch.
        Cache::shouldReceive('forget')
            ->with(\Mockery::pattern('/^rate:USD:branch:\d+$/'));
        Cache::shouldReceive('forget')
            ->with('exchange_rates_for_transactions');
        // Rate invalidation now also flushes the 'rates' tag; the array test
        // store is taggable, so mock the tag path too.
        Cache::shouldReceive('getStore')->zeroOrMoreTimes()->andReturn(new ArrayStore);
        Cache::shouldReceive('tags')->zeroOrMoreTimes()->andReturnSelf();
        Cache::shouldReceive('flush')->zeroOrMoreTimes();

        app(RateManagementService::class)->overrideRate('USD', '4.6000', '4.7000', $manager);
    }
}
