<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\ExchangeRateHistory;
use App\Models\User;
use App\Services\Transaction\RateManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Branch isolation for rate reads and the copy-previous write path.
 *
 * A company-wide scope must resolve to the company card only (branch overrides
 * belong to their branch), and the copy-previous write must never read another
 * branch's history or overwrite another branch's card.
 */
class RateManagementServiceBranchScopeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function company_wide_rate_list_excludes_branch_overrides(): void
    {
        Currency::factory()->create(['code' => 'USD']);
        $branch = Branch::factory()->create();

        ExchangeRate::factory()->create([
            'currency_code' => 'USD',
            'rate_buy' => '4.5000',
            'rate_sell' => '4.6000',
            'fetched_at' => now(),
        ]);
        ExchangeRate::factory()->create([
            'currency_code' => 'USD',
            'branch_id' => $branch->id,
            'rate_buy' => '4.7000',
            'rate_sell' => '4.8000',
            'fetched_at' => now(),
        ]);

        $service = app(RateManagementService::class);

        $companyRates = $service->getCurrentRates();

        $this->assertCount(1, $companyRates);
        $this->assertNull($companyRates->first()->branch_id);
        $this->assertSame('4.50000000', (string) $companyRates->first()->rate_buy);

        // A branch scope still overlays its own card on top.
        $branchRates = $service->getCurrentRates($branch->id);

        $this->assertCount(1, $branchRates);
        $this->assertSame($branch->id, $branchRates->first()->branch_id);
        $this->assertSame('4.70000000', (string) $branchRates->first()->rate_buy);
    }

    #[Test]
    public function company_wide_copy_previous_ignores_branch_history_and_branch_cards(): void
    {
        Currency::factory()->create(['code' => 'USD']);
        Currency::factory()->create(['code' => 'EUR']);
        $branch = Branch::factory()->create();

        $targetDate = now()->subDay()->toDateString();

        ExchangeRateHistory::factory()->create([
            'currency_code' => 'USD',
            'branch_id' => null,
            'rate' => '4.5500',
            'effective_date' => $targetDate,
        ]);
        // Another branch's history for the same day must not be copied into the
        // company card.
        ExchangeRateHistory::factory()->create([
            'currency_code' => 'EUR',
            'branch_id' => $branch->id,
            'rate' => '5.0500',
            'effective_date' => $targetDate,
        ]);

        $companyCard = ExchangeRate::factory()->create([
            'currency_code' => 'USD',
            'rate_buy' => '4.4000',
            'rate_sell' => '4.5000',
            'fetched_at' => now()->subDay(),
        ]);
        $branchCard = ExchangeRate::factory()->create([
            'currency_code' => 'EUR',
            'branch_id' => $branch->id,
            'rate_buy' => '4.9000',
            'rate_sell' => '5.0000',
            'fetched_at' => now()->subDay(),
        ]);

        $result = app(RateManagementService::class)->copyPreviousRates($targetDate);

        $this->assertTrue($result['success']);
        $this->assertSame(['USD'], collect($result['rates'])->pluck('currency')->all());

        $companyCard->refresh();
        $this->assertSame("copied_from_{$targetDate}", $companyCard->source);

        // The branch card was neither read nor written by the company copy.
        $branchCard->refresh();
        $this->assertSame('4.90000000', (string) $branchCard->rate_buy);
        $this->assertSame('api', $branchCard->source);
    }

    #[Test]
    public function branch_copy_previous_only_uses_that_branchs_history_and_card(): void
    {
        Currency::factory()->create(['code' => 'USD']);
        Currency::factory()->create(['code' => 'EUR']);
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();

        $targetDate = now()->subDay()->toDateString();

        ExchangeRateHistory::factory()->create([
            'currency_code' => 'USD',
            'branch_id' => null,
            'rate' => '4.5500',
            'effective_date' => $targetDate,
        ]);
        ExchangeRateHistory::factory()->create([
            'currency_code' => 'USD',
            'branch_id' => $branchA->id,
            'rate' => '4.9000',
            'effective_date' => $targetDate,
        ]);

        $companyCard = ExchangeRate::factory()->create([
            'currency_code' => 'USD',
            'rate_buy' => '4.4000',
            'rate_sell' => '4.5000',
            'fetched_at' => now()->subDay(),
        ]);
        $branchCard = ExchangeRate::factory()->create([
            'currency_code' => 'USD',
            'branch_id' => $branchA->id,
            'rate_buy' => '4.4000',
            'rate_sell' => '4.5000',
            'fetched_at' => now()->subDay(),
        ]);
        ExchangeRate::factory()->create([
            'currency_code' => 'EUR',
            'branch_id' => $branchB->id,
            'rate_buy' => '5.0000',
            'rate_sell' => '5.1000',
            'fetched_at' => now()->subDay(),
        ]);

        $result = app(RateManagementService::class)->copyPreviousRates($targetDate, $branchA->id);

        $this->assertTrue($result['success']);
        $this->assertSame(['USD'], collect($result['rates'])->pluck('currency')->all());

        // Branch A's own card took branch A's mid (4.9000), re-derived with the
        // configured spread, and the company card stayed untouched.
        $branchCard->refresh();
        $this->assertSame("copied_from_{$targetDate}", $branchCard->source);
        $this->assertNotSame(
            '4.40000000',
            (string) $branchCard->rate_buy,
            'Branch card should have been refreshed from the branch history mid.'
        );

        $companyCard->refresh();
        $this->assertSame('api', $companyCard->source);
        $this->assertSame('4.40000000', (string) $companyCard->rate_buy);
    }

    #[Test]
    public function company_wide_override_invalidates_cardless_branch_cache(): void
    {
        Currency::factory()->create(['code' => 'USD']);
        // The branch has NO card row of its own: its per-currency cache key
        // holds a cached fallback to the company rate.
        $cardlessBranch = Branch::factory()->create();

        ExchangeRate::factory()->create([
            'currency_code' => 'USD',
            'rate_buy' => '4.4000',
            'rate_sell' => '4.5000',
            'fetched_at' => now(),
        ]);

        $service = app(RateManagementService::class);

        // Populate the branch-scoped cache entry with the company fallback.
        $cached = $service->getRateCard('USD', $cardlessBranch->id);
        $this->assertSame('4.40000000', (string) $cached->rate_buy);

        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $result = $service->overrideRate('USD', '4.6000', '4.7000', $admin);

        $this->assertTrue($result->success);

        // Without enumerating every branch id, the cardless branch's cached
        // key would keep serving the pre-override company rate until TTL.
        $served = $service->getRateCard('USD', $cardlessBranch->id);
        $this->assertSame('4.60000000', (string) $served->rate_buy);
    }
}
