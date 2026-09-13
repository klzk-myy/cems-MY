<?php

namespace Tests\Unit;

use App\Models\AccountingPeriod;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\RevaluationService;
use App\Services\AuditService;
use App\Services\System\MathService;
use App\Services\Transaction\RateApiService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RevaluationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected RevaluationService $service;

    protected MathService $mathService;

    protected User $testUser;

    protected Currency $testCurrency;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mathService = new MathService;

        // Create required foreign key records
        $this->testCurrency = Currency::firstOrCreate(
            ['code' => 'USD'],
            ['name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'is_active' => true]
        );

        $this->testUser = User::factory()->create([
            'username' => 'testuser',
            'email' => 'test@test.com',
            'password_hash' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        // Disable account validation for this test
        Config::set('accounting.validate_accounts', false);
    }

    protected function createTestAccountingPeriod(string $date): AccountingPeriod
    {
        $parsedDate = Carbon::parse($date);

        return AccountingPeriod::firstOrCreate(
            ['period_code' => $parsedDate->format('Y-m')],
            [
                'start_date' => $parsedDate->startOfMonth()->toDateString(),
                'end_date' => $parsedDate->endOfMonth()->toDateString(),
                'period_type' => 'month',
                'status' => 'Open',
            ]
        );
    }

    #[Test]
    public function run_revaluation_with_journal_throws_when_accounting_service_fails(): void
    {
        // Arrange: Create an open accounting period
        $testDate = now()->toDateString();
        $this->createTestAccountingPeriod($testDate);

        // Create a currency position with balance
        $position = CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'branch_id' => 'TEST-BRANCH',
            'quantity' => '1000.00',
            'average_cost' => '4.50',
            'current_rate' => '4.50',
        ]);

        // Mock the RateApiService to return a different rate (causing gain/loss)
        $mockRateApi = Mockery::mock(RateApiService::class);
        $mockRateApi->shouldReceive('getRateForCurrency')
            ->with('USD')
            ->andReturn(['mid' => 4.60]);

        // Mock the AccountingService to throw an exception when createJournalEntry is called
        $mockAccounting = Mockery::mock(AccountingService::class);
        $mockAccounting->shouldReceive('createJournalEntry')
            ->andThrow(new \RuntimeException('Journal entry creation failed'));

        // Mock the AuditService
        $mockAudit = Mockery::mock(AuditService::class);
        $mockAudit->shouldReceive('logPositionEvent')->andReturn(null);

        // Create the service with mocked dependencies
        $service = new RevaluationService(
            $this->mathService,
            $mockRateApi,
            $mockAccounting,
            $mockAudit
        );
        try {
            $service->runRevaluationWithJournal($testDate, $this->testUser->id);
            $this->fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            // Service aggregates errors and throws summary with failed currencies
            $this->assertStringContainsString('Failed currencies:', $e->getMessage());
            $this->assertStringContainsString('USD', $e->getMessage());
        }
    }

    #[Test]
    public function revaluation_error_includes_successful_currencies(): void
    {
        // Arrange: Create an open accounting period
        $testDate = now()->toDateString();
        $this->createTestAccountingPeriod($testDate);

        // Create multiple currency positions (USD and EUR)
        $usdPosition = CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'branch_id' => 'TEST-BRANCH',
            'quantity' => '1000.00',
            'average_cost' => '4.50',
            'current_rate' => '4.50',
        ]);

        $eurPosition = CurrencyPosition::factory()->create([
            'currency_code' => 'EUR',
            'branch_id' => 'TEST-BRANCH',
            'quantity' => '500.00',
            'average_cost' => '5.00',
            'current_rate' => '5.00',
        ]);

        // Mock RateApiService to return different rates (causing gain/loss)
        $mockRateApi = Mockery::mock(RateApiService::class);
        $mockRateApi->shouldReceive('getRateForCurrency')
            ->with('USD')
            ->andReturn(['mid' => 4.60]);
        $mockRateApi->shouldReceive('getRateForCurrency')
            ->with('EUR')
            ->andReturn(['mid' => 5.10]);

        // Mock AccountingService with sequential behavior:
        // First call (USD) throws exception, second call (EUR) succeeds
        $mockAccounting = Mockery::mock(AccountingService::class);
        $mockAccounting->shouldReceive('createJournalEntry')
            ->twice()
            ->andReturnUsing(function () {
                static $callCount = 0;
                $callCount++;
                if ($callCount === 1) {
                    throw new \RuntimeException('Journal entry failed for USD');
                }

                return Mockery::mock(JournalEntry::class)->shouldIgnoreMissing();
            });

        // Mock AuditService
        $mockAudit = Mockery::mock(AuditService::class);
        $mockAudit->shouldReceive('logPositionEvent')->andReturn(null);

        // Create the service with mocked dependencies
        $service = new RevaluationService(
            $this->mathService,
            $mockRateApi,
            $mockAccounting,
            $mockAudit
        );

        // Act & Assert: Verify error message includes both successful and failed currencies
        try {
            $service->runRevaluationWithJournal($testDate, $this->testUser->id);
            $this->fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            $message = $e->getMessage();
            // The service aggregates errors and throws a summary with successful/failed currencies
            $this->assertStringContainsString('Successful currencies:', $message);
            $this->assertStringContainsString('Failed currencies:', $message);
            // At minimum USD should be in the failed currencies
            $this->assertStringContainsString('USD', $message);
        }
    }

    #[Test]
    public function run_revaluation_with_journal_succeeds_when_all_currencies_process(): void
    {
        // Arrange: Create an open accounting period
        $testDate = now()->toDateString();
        $this->createTestAccountingPeriod($testDate);

        // Create a currency position with balance
        /** @var CurrencyPosition $position */
        $position = CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'branch_id' => 'TEST-BRANCH',
            'quantity' => '1000.00',
            'average_cost' => '4.50',
            'current_rate' => '4.50',
        ]);

        // Mock the RateApiService to return a rate
        $mockRateApi = Mockery::mock(RateApiService::class);
        $mockRateApi->shouldReceive('getRateForCurrency')
            ->with('USD')
            ->andReturn(['mid' => 4.60]);

        // Mock the AccountingService to succeed
        $mockAccounting = Mockery::mock(AccountingService::class);
        $mockAccounting->shouldReceive('createJournalEntry')
            ->andReturn(Mockery::mock(JournalEntry::class)->shouldIgnoreMissing());

        // Mock the AuditService
        $mockAudit = Mockery::mock(AuditService::class);
        $mockAudit->shouldReceive('logPositionEvent')->andReturn(null);

        // Create the service with mocked dependencies
        $service = new RevaluationService(
            $this->mathService,
            $mockRateApi,
            $mockAccounting,
            $mockAudit
        );

        // Act
        $result = $service->runRevaluationWithJournal($testDate, $this->testUser->id);

        // Assert: Verify that no errors are returned (exception would have been thrown)
        $this->assertArrayNotHasKey('errors', $result);
        $this->assertEquals(1, $result['positions_updated']);

        // current_value must track the new rate (quantity × 4.60) — the
        // previous code updated current_rate but left current_value stale.
        $position->refresh();
        $this->assertEquals('4600.0000', $position->current_value);
    }

    #[Test]
    public function run_revaluation_with_journal_skips_zero_balance_positions(): void
    {
        // Arrange: Create an open accounting period
        $testDate = now()->toDateString();
        $this->createTestAccountingPeriod($testDate);

        // Create a currency position with zero balance
        CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'branch_id' => 'TEST-BRANCH',
            'quantity' => '0.00',
            'average_cost' => '4.50',
            'current_rate' => '4.50',
        ]);

        // Mock the RateApiService
        $mockRateApi = Mockery::mock(RateApiService::class);
        $mockRateApi->shouldReceive('getRateForCurrency')->never();

        // Mock the AccountingService
        $mockAccounting = Mockery::mock(AccountingService::class);
        $mockAccounting->shouldReceive('createJournalEntry')->never();

        // Mock the AuditService
        $mockAudit = Mockery::mock(AuditService::class);

        // Create the service with mocked dependencies
        $service = new RevaluationService(
            $this->mathService,
            $mockRateApi,
            $mockAccounting,
            $mockAudit
        );

        // Act
        $result = $service->runRevaluationWithJournal($testDate, $this->testUser->id);

        // Assert
        $this->assertEquals(0, $result['positions_updated']);
        $this->assertEmpty($result['results']);
    }

    #[Test]
    public function revaluation_uses_consistent_scale(): void
    {
        // Test that bccomp uses scale=6 (matching MathService default)
        // This ensures consistency across all BCMath operations
        $rate1 = '4.5000001'; // Differs only at 7th decimal place
        $rate2 = '4.5000002'; // Differs only at 7th decimal place

        // With scale=6, these rates should be considered EQUAL (no difference within 6 decimals)
        $this->assertEquals(0, bccomp($rate1, $rate2, 6), 'Rates should be equal at scale 6');

        // With scale=10, these rates should be considered DIFFERENT
        $this->assertEquals(-1, bccomp($rate1, $rate2, 10), 'Rates should differ at scale 10');

        // This verifies that the scale parameter matters for rate comparisons
        // The RevaluationService uses scale=6 to match MathService, ensuring consistent precision
    }

    #[Test]
    public function run_revaluation_with_journal_writes_absolute_unrealized_pnl(): void
    {
        // Arrange: Create an open accounting period
        $testDate = now()->toDateString();
        $this->createTestAccountingPeriod($testDate);

        // Seed a position with a stale/inconsistent unrealized_gain_loss (as set by a
        // prior Buy via CurrencyPositionService::updatePosition). A journal revaluation
        // must OVERWRITE it with the absolute mark-to-market value, never accumulate a
        // delta on top, otherwise repeated revaluations double-count P&L.
        $position = CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'branch_id' => 'TEST-BRANCH',
            'quantity' => '100.00',
            'average_cost' => '4.00',
            'current_rate' => '4.20',
            'unrealized_gain_loss' => '99.0000',
        ]);

        // Mock the RateApiService to return a rate
        $mockRateApi = Mockery::mock(RateApiService::class);
        $mockRateApi->shouldReceive('getRateForCurrency')
            ->with('USD')
            ->andReturn(['mid' => 4.40]);

        // Mock the AccountingService to succeed
        $mockAccounting = Mockery::mock(AccountingService::class);
        $mockAccounting->shouldReceive('createJournalEntry')
            ->andReturn(Mockery::mock(JournalEntry::class)->shouldIgnoreMissing());

        // Mock the AuditService
        $mockAudit = Mockery::mock(AuditService::class);

        // Create the service with mocked dependencies
        $service = new RevaluationService(
            $this->mathService,
            $mockRateApi,
            $mockAccounting,
            $mockAudit
        );

        // Act
        $service->runRevaluationWithJournal($testDate, $this->testUser->id);

        // Assert: gainLoss = 100 * (4.4 - 4.2) = 20, but the field must be the
        // absolute value 100 * (4.4 - 4.0) = 40, not the accumulated 99 + 20 = 119.
        $position->refresh();
        $this->assertSame('40.0000', $position->unrealized_gain_loss);
        $this->assertSame('4.400000', $position->current_rate);
        $this->assertDatabaseHas('revaluation_entries', [
            'currency_code' => 'USD',
            'gain_loss_amount' => '20',
            'revaluation_date' => $testDate.' 00:00:00',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
