<?php

namespace Tests\Unit;

use App\Models\AccountingPeriod;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\AuditService;
use App\Services\MathService;
use App\Services\RateApiService;
use App\Services\RevaluationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
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

        return AccountingPeriod::factory()->create([
            'period_code' => $parsedDate->format('Y-m'),
            'start_date' => $parsedDate->startOfMonth()->toDateString(),
            'end_date' => $parsedDate->endOfMonth()->toDateString(),
            'period_type' => 'month',
            'status' => 'open',
        ]);
    }

    public function test_run_revaluation_with_journal_throws_when_accounting_service_fails(): void
    {
        // Arrange: Create an open accounting period
        $testDate = now()->toDateString();
        $this->createTestAccountingPeriod($testDate);

        // Create a currency position with balance
        $position = CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'till_id' => 'TEST-TILL',
            'balance' => '1000.00',
            'avg_cost_rate' => '4.50',
            'last_valuation_rate' => '4.50',
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

        // Act & Assert: Verify that an exception is thrown instead of silently returning errors
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Journal entry creation failed');

        $service->runRevaluationWithJournal($testDate, $this->testUser->id);
    }

    public function test_run_revaluation_with_journal_succeeds_when_all_currencies_process(): void
    {
        // Arrange: Create an open accounting period
        $testDate = now()->toDateString();
        $this->createTestAccountingPeriod($testDate);

        // Create a currency position with balance
        $position = CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'till_id' => 'TEST-TILL',
            'balance' => '1000.00',
            'avg_cost_rate' => '4.50',
            'last_valuation_rate' => '4.50',
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
    }

    public function test_run_revaluation_with_journal_skips_zero_balance_positions(): void
    {
        // Arrange: Create an open accounting period
        $testDate = now()->toDateString();
        $this->createTestAccountingPeriod($testDate);

        // Create a currency position with zero balance
        CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'till_id' => 'TEST-TILL',
            'balance' => '0.00',
            'avg_cost_rate' => '4.50',
            'last_valuation_rate' => '4.50',
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

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
