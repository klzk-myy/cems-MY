<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Exceptions\Domain\TransactionValidationException;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Models\TillBalance;
use App\Models\User;
use App\Services\Contracts\RateManagementServiceInterface;
use App\Services\Contracts\TransactionCreationServiceInterface;
use App\Services\Transaction\RateManagementService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ManualBookingRateValidationTest extends TestCase
{
    use DatabaseTransactions;

    protected Branch $branch;

    protected Counter $counter;

    protected Customer $customer;

    protected User $teller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create([
            'code' => 'HQ'.substr(uniqid(), -4),
            'is_active' => true,
        ]);

        $this->counter = Counter::factory()->create([
            'branch_id' => $this->branch->id,
        ]);

        $this->teller = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        TillBalance::factory()->create([
            'till_id' => $this->counter->code,
            'branch_id' => $this->branch->id,
            'currency_code' => 'USD',
            'opening_balance' => '10000',
            'date' => today(),
            'opened_by' => $this->teller->id,
        ]);

        $this->customer = Customer::factory()->create([
            'risk_rating' => 'Low',
            'cdd_level' => 'Simplified',
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function bookingData(string $rate): array
    {
        return [
            'type' => 'buy',
            'currency_code' => 'USD',
            'amount_foreign' => '100.00',
            'rate' => $rate,
            'customer_id' => $this->customer->id,
            'till_id' => $this->counter->code,
        ];
    }

    #[Test]
    public function manual_booking_with_deviating_rate_is_blocked(): void
    {
        // Simulate a market rate of 4.50 so the submitted 9.00 is aberrant.
        ExchangeRate::factory()->create([
            'currency_code' => 'USD',
            'rate_buy' => '4.5000',
            'rate_sell' => '4.6000',
            'branch_id' => $this->branch->id,
            'fetched_at' => now(),
        ]);

        $this->actingAs($this->teller);

        $service = app(TransactionCreationServiceInterface::class);

        try {
            $service->prepareAndCreate($this->bookingData('9.00'), $this->teller->id);

            $this->fail('Expected TransactionValidationException for deviating rate');
        } catch (TransactionValidationException $e) {
            $this->assertStringContainsStringIgnoringCase('deviation', $e->getMessage());
        }
    }

    #[Test]
    public function small_rate_deviations_pass_the_tolerance_check(): void
    {
        ExchangeRate::factory()->create([
            'currency_code' => 'USD',
            'rate_buy' => '4.5000',
            'rate_sell' => '4.6000',
            'fetched_at' => now(),
        ]);

        $service = app(RateManagementService::class);

        // Exact market buy rate must always pass.
        $check = $service->validateTransactionRate('4.5000', 'USD', 'buy');
        $this->assertTrue($check['valid']);

        // A huge deviation must be blocked.
        $check = $service->validateTransactionRate('9.0000', 'USD', 'buy');
        $this->assertFalse($check['valid']);
    }

    #[Test]
    public function bookings_pass_through_when_no_market_rate_is_configured(): void
    {
        // No ExchangeRate rows exist: the guard must skip, not block.
        $mockedGate = \Mockery::mock(RateManagementServiceInterface::class);
        $mockedGate->shouldReceive('validateTransactionRate')
            ->once()
            ->andReturn([
                'valid' => true,
                'reason' => null,
                'deviation_percent' => null,
                'max_allowed' => null,
            ]);
        $this->app->instance(RateManagementServiceInterface::class, $mockedGate);

        $service = app(TransactionCreationServiceInterface::class);

        try {
            $service->prepareAndCreate($this->bookingData('9.00'), $this->teller->id);
        } catch (TransactionValidationException $e) {
            $this->assertStringNotContainsStringIgnoringCase('deviation', $e->getMessage());
        } catch (\Throwable $e) {
            // Any later failure (sanctions screening, till accounting, ...)
            // proves the rate gate itself did not block the booking.
            $this->assertStringNotContainsStringIgnoringCase('deviation', $e->getMessage());
        }
    }
}
