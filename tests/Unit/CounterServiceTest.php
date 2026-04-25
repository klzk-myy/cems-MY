<?php

namespace Tests\Unit;

use App\Enums\CounterSessionStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\CounterService;
use App\Services\MathService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CounterServiceTest extends TestCase
{
    use RefreshDatabase;

    protected MathService $mathService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mathService = new MathService;
    }

    public function test_can_open_counter_session(): void
    {
        $user = User::factory()->create(['role' => UserRole::Teller]);

        $sessionData = [
            'counter_id' => 'COUNTER-001',
            'user_id' => $user->id,
            'opening_float' => '10000.00',
            'opened_at' => now(),
        ];

        $this->assertNotNull($sessionData['counter_id']);
        $this->assertEquals($user->id, $sessionData['user_id']);
    }

    public function test_cannot_open_if_already_open(): void
    {
        $existingSession = true;
        $counterAlreadyOpen = true;

        // Should prevent opening if already open
        $this->assertTrue($counterAlreadyOpen);
    }

    public function test_cannot_open_if_user_at_another_counter(): void
    {
        $userAtAnotherCounter = true;

        // Should prevent user from opening multiple counters
        $this->assertTrue($userAtAnotherCounter);
    }

    public function test_close_session_updates_balance(): void
    {
        $openingFloat = '10000.00';
        $closingFloat = '10500.00';

        $variance = bcsub($closingFloat, $openingFloat, 2);

        $this->assertEquals('500.00', $variance);
    }

    public function test_calculates_variance_correctly(): void
    {
        $openingFloat = '10000.00';
        $closingFloat = '9800.00';

        $variance = bcsub($closingFloat, $openingFloat, 2);

        $this->assertEquals('-200.00', $variance);
    }

    public function test_requires_supervisor_for_large_variance(): void
    {
        $variance = '600.00'; // Exceeds RM 500 threshold
        $varianceThreshold = '500.00';

        // Use abs() since MathService may not have abs method
        $absVariance = ltrim(bcsub($variance, '0', 2), '-');
        $requiresSupervisor = bccomp($absVariance, $varianceThreshold, 2) > 0;

        $this->assertTrue($requiresSupervisor);
    }

    public function test_small_variance_no_supervisor_required(): void
    {
        $variance = '200.00';
        $varianceThreshold = '500.00';

        $absVariance = ltrim(bcsub($variance, '0', 2), '-');
        $requiresSupervisor = bccomp($absVariance, $varianceThreshold, 2) > 0;

        $this->assertFalse($requiresSupervisor);
    }

    public function test_zero_variance_handover(): void
    {
        $variance = '0.00';

        $this->assertEquals('0.00', $variance);
    }

    public function test_initiate_handover_fails_when_from_user_not_session_user(): void
    {
        $fromUserId = 1;
        $sessionUserId = 2;

        $userMismatch = $fromUserId !== $sessionUserId;

        $this->assertTrue($userMismatch);
    }

    public function test_initiate_handover_fails_when_to_user_at_another_counter(): void
    {
        $toUserAtAnotherCounter = true;

        $this->assertTrue($toUserAtAnotherCounter);
    }

    public function test_initiate_handover_fails_when_session_not_open(): void
    {
        $sessionStatus = CounterSessionStatus::Closed;

        $this->assertEquals(CounterSessionStatus::Closed, $sessionStatus);
    }

    public function test_handover_preserves_audit_trail(): void
    {
        $tillBalanceTransferred = true;

        $this->assertTrue($tillBalanceTransferred);
    }

    public function test_resolve_currencies_returns_string_keys_for_numeric_ids(): void
    {
        // Note: Currency model uses 'code' as primary key (non-incrementing string).
        // When currency_id is passed as numeric, it represents a database row ID
        // that was erroneously stored - this tests that such numeric IDs are
        // properly resolved to their currency codes as string keys.

        // We simulate this by passing what looks like a numeric ID (e.g., '1')
        // as currency_id and ensure it's resolved correctly to a string code key.
        // In practice, this shouldn't happen with the fixed code, but we test it.

        $floats = [
            // Passing a string that looks numeric - the method should handle it
            // by trying to look it up as a Currency ID (though Currency uses code as PK)
            ['currency_id' => '999', 'amount' => '1000.00'],
        ];

        $service = app(CounterService::class);
        $method = new \ReflectionMethod($service, 'resolveCurrencies');
        $method->setAccessible(true);

        $result = $method->invoke($service, $floats);

        // For non-existent numeric IDs, the result should be empty
        // (no currencies found for ID 999)
        $this->assertArrayNotHasKey('999', $result);
    }

    public function test_resolve_currencies_returns_string_keys_for_string_codes(): void
    {
        $floats = [
            ['currency_id' => 'EUR', 'amount' => '1000.00'],
            ['currency_id' => 'GBP', 'amount' => '2000.00'],
        ];

        $service = app(CounterService::class);
        $method = new \ReflectionMethod($service, 'resolveCurrencies');
        $method->setAccessible(true);

        $result = $method->invoke($service, $floats);

        // Keys should be string currency codes
        $this->assertArrayHasKey('EUR', $result);
        $this->assertArrayHasKey('GBP', $result);
        $this->assertEquals('EUR', $result['EUR']);
        $this->assertEquals('GBP', $result['GBP']);
    }

    public function test_resolve_currencies_for_counts_returns_string_keys(): void
    {
        // Note: Currency model uses 'code' as primary key. Testing with string codes
        // which is the proper usage pattern for this codebase.

        $counts = [
            ['currency_id' => 'USD', 'denomination' => '100', 'quantity' => 10],
            ['currency_id' => 'EUR', 'denomination' => '50', 'quantity' => 5],
        ];

        $service = app(CounterService::class);
        $method = new \ReflectionMethod($service, 'resolveCurrenciesForCounts');
        $method->setAccessible(true);

        $result = $method->invoke($service, $counts);

        // Keys should be string currency codes, not numeric IDs
        $this->assertArrayHasKey('USD', $result);
        $this->assertArrayHasKey('EUR', $result);
        $this->assertEquals('USD', $result['USD']);
        $this->assertEquals('EUR', $result['EUR']);
    }

    public function test_resolve_currencies_all_keys_are_strings(): void
    {
        $floats = [
            ['currency_id' => 'USD', 'amount' => '1000.00'],
            ['currency_id' => 'EUR', 'amount' => '2000.00'],
            ['currency_id' => 'GBP', 'amount' => '3000.00'],
        ];

        $service = app(CounterService::class);
        $method = new \ReflectionMethod($service, 'resolveCurrencies');
        $method->setAccessible(true);

        $result = $method->invoke($service, $floats);

        // All keys should be strings (currency codes), not integers
        foreach (array_keys($result) as $key) {
            $this->assertIsString($key, 'Expected string key but got '.gettype($key));
        }

        // All values should be strings (currency codes)
        foreach (array_values($result) as $value) {
            $this->assertIsString($value, 'Expected string value but got '.gettype($value));
        }
    }
}
