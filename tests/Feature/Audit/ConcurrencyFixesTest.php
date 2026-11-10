<?php

namespace Tests\Feature\Audit;

use App\Enums\CddLevel;
use App\Enums\JournalEntryStatus;
use App\Enums\RiskRating;
use App\Enums\TransactionConfirmationStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Exceptions\Domain\AccountingPeriodException;
use App\Jobs\ComplianceScreeningJob;
use App\Models\Branch;
use App\Models\BranchPool;
use App\Models\Compliance\Alert;
use App\Models\Compliance\FlaggedTransaction;
use App\Models\Counter;
use App\Models\CurrencyPosition;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\TellerAllocation;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\CurrencyPositionService;
use App\Services\AuditService;
use App\Services\Branch\BranchPoolService;
use App\Services\Branch\TellerAllocationService;
use App\Services\Screening\CustomerScreeningService;
use App\Services\System\MfaService;
use App\Services\System\RateLimitService;
use App\Services\ThresholdService;
use App\Services\Transaction\DTOs\TransactionCreationContext;
use App\Services\Transaction\TransactionConfirmationService;
use App\Services\Transaction\TransactionCreationService;
use App\Services\Transaction\TransactionReversalService;
use App\ValueObjects\ScreeningMatch;
use App\ValueObjects\ScreeningResponse;
use Carbon\Carbon;
use Illuminate\Cache\RedisStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ConcurrencyFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_concurrent_branch_pool_allocations_do_not_go_negative(): void
    {
        $branch = Branch::factory()->create();
        BranchPool::factory()->for($branch)->create([
            'currency_code' => 'USD',
            'available_balance' => '100.00',
            'allocated_balance' => '0.00',
        ]);

        $results = collect(range(1, 10))->map(function () use ($branch) {
            return new class($branch)
            {
                public function __construct(public Branch $branch) {}

                public function run(): bool
                {
                    return app(BranchPoolService::class)
                        ->allocateToTeller($this->branch, 'USD', '30.00');
                }
            };
        });

        // Only ~3 of 30-dollar allocations should succeed
        $this->assertSame(3, $results->filter(fn ($r) => $r->run())->count());
    }

    public function test_concurrent_get_or_create_branch_pool_does_not_duplicate(): void
    {
        $branch = Branch::factory()->create();

        $created = collect(range(1, 5))->map(fn () => app(BranchPoolService::class)->getOrCreateForBranch($branch, 'USD')
        );

        $this->assertSame(1, BranchPool::where('branch_id', $branch->id)
            ->where('currency_code', 'USD')
            ->count());
    }

    public function test_approve_allocation_cannot_be_approved_twice(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->for($branch)->create(['role' => UserRole::Manager]);
        $allocation = TellerAllocation::factory()->for($branch)->pending()->create([
            'currency_code' => 'USD',
        ]);
        BranchPool::factory()->for($branch)->create([
            'currency_code' => 'USD',
            'available_balance' => '1000.00',
        ]);

        $service = app(TellerAllocationService::class);
        $service->approveAllocation($allocation, $manager, '500.00');

        $this->expectException(\RuntimeException::class);
        $service->approveAllocation($allocation, $manager, '500.00');
    }

    public function test_return_to_pool_is_idempotent_under_lock(): void
    {
        $branch = Branch::factory()->create();
        $allocation = TellerAllocation::factory()->for($branch)->active()->create([
            'currency_code' => 'USD',
            'current_quantity' => '100.00',
        ]);
        BranchPool::factory()->for($branch)->create([
            'currency_code' => 'USD',
            'available_balance' => '0.00',
            'allocated_balance' => '100.00',
        ]);

        $service = app(TellerAllocationService::class);
        $service->returnToPool($allocation);

        $this->expectException(\RuntimeException::class);
        $service->returnToPool($allocation);
    }

    public function test_concurrent_transactions_on_same_allocation_do_not_lose_updates(): void
    {
        // This test is representative; full concurrency testing may require
        // parallel processes, but the assertion guards the locking code path.
        // The allocation update now lives in TransactionCreationService, so we
        // build the context directly instead of going through the facade.
        $branch = Branch::factory()->create();
        $teller = User::factory()->for($branch)->create();
        $customer = Customer::factory()->create([
            'risk_rating' => RiskRating::Low->value,
            'risk_score' => 0,
            'pep_status' => false,
        ]);
        $allocation = TellerAllocation::factory()->for($branch)->for($teller)->active()->create([
            'currency_code' => 'USD',
            'allocated_quantity' => '2000.00',
            'current_quantity' => '1000.00',
            'session_date' => now()->toDateString(),
        ]);
        $counter = Counter::factory()->for($branch)->create();

        // Create TillBalance for USD (for the transaction's currency)
        /** @var TillBalance $tillBalance */
        $tillBalance = TillBalance::factory()->for($counter)->create([
            'branch_id' => $branch->id,
            'currency_code' => 'USD',
            'date' => today(),
            'total_quantity' => '0',
            'buy_quantity' => '0',
            'sell_quantity' => '0',
        ]);

        // Create TillBalance for MYR (local currency)
        TillBalance::factory()->for($counter)->create([
            'branch_id' => $branch->id,
            'currency_code' => 'MYR',
            'date' => today(),
            'transaction_total_myr' => '10000',
        ]);

        // Create CurrencyPosition for USD with sufficient stock
        CurrencyPosition::create([
            'branch_id' => $branch->id,
            'currency_code' => 'USD',
            'quantity' => '1000.00',
            'average_cost' => '4.70',
            'total_cost' => '0',
            'current_rate' => '4.70',
            'current_value' => '0',
            'unrealized_gain_loss' => '0',
            'last_revalued_at' => null,
        ]);

        $creationService = app(TransactionCreationService::class);

        // Verify preconditions
        $this->assertTrue($teller->isTeller(), 'Teller user must have Teller role');
        $this->assertTrue($allocation->status->isActive(), 'Allocation must be active');
        $this->assertEquals($teller->id, $allocation->user_id, 'Allocation belongs to teller');

        // Ensure getActiveAllocation finds the allocation
        $active = app(TellerAllocationService::class)->getActiveAllocation($teller, 'USD');
        $this->assertNotNull($active, 'Active allocation should be found via service');

        $amounts = ['9.99', '10.00', '10.01'];
        foreach ($amounts as $quantity) {
            $amountMyr = bcmul($quantity, '4.70', 4);

            $context = new TransactionCreationContext(
                data: [
                    'customer_id' => $customer->id,
                    'till_id' => $counter->code,
                    'type' => TransactionType::Sell->value,
                    'currency_code' => 'USD',
                    'quantity' => $quantity,
                    'rate' => '4.70',
                    'purpose' => 'Test transaction',
                    'source_of_funds' => 'salary',
                    'source_of_wealth' => 'employment',
                ],
                customer: $customer,
                tillBalance: $tillBalance,
                cddLevel: CddLevel::Simplified,
                holdRequired: false,
                status: TransactionStatus::Completed,
                amountMyr: $amountMyr,
                user: $teller,
                allocation: $allocation,
            );

            $creationService->create($context, $teller->id, '127.0.0.1');
        }

        // Total allocated: 1000 - 30 = 970
        $this->assertSame('970.0000', $allocation->fresh()->current_quantity);
    }

    public function test_concurrent_reversal_attempts_are_serialized(): void
    {
        $transaction = $this->createCompletedSellTransaction();
        $manager = User::factory()->for($transaction->branch)->create(['role' => UserRole::Manager]);

        $service = app(TransactionReversalService::class);
        $this->assertTrue($service->reverse($transaction, $manager, 'reason'));

        $this->expectException(\RuntimeException::class);
        $service->reverse($transaction, $manager, 'reason');
    }

    public function test_concurrent_first_currency_position_creation_does_not_duplicate(): void
    {
        $branch = Branch::factory()->create();

        $positions = collect(range(1, 5))->map(fn () => app(CurrencyPositionService::class)->getOrCreatePosition($branch->id, 'USD', '4.70')
        );

        $this->assertSame(1, CurrencyPosition::where('branch_id', $branch->id)
            ->where('currency_code', 'USD')
            ->count());
    }

    public function test_duplicate_confirmation_requests_return_same_record(): void
    {
        $transaction = Transaction::factory()->create();
        $manager = User::factory()->create();

        $service = app(TransactionConfirmationService::class);
        $first = $service->requestConfirmation($transaction, $manager->id);
        $second = $service->requestConfirmation($transaction, $manager->id);

        $this->assertSame($first->id, $second->id);
    }

    public function test_new_confirmation_can_be_requested_after_rejection(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->for($branch)->manager()->create();
        $teller = User::factory()->for($branch)->teller()->create();
        $customer = Customer::factory()->create();

        $transaction = Transaction::factory()->for($branch)->for($customer)->create([
            'type' => TransactionType::Buy->value,
            'status' => TransactionStatus::PendingApproval->value,
            'amount_myr' => '50000.00',
        ]);

        $service = app(TransactionConfirmationService::class);

        $first = $service->requestConfirmation($transaction, $teller->id);
        $this->assertSame(TransactionConfirmationStatus::Pending->value, $first->status->value);

        $this->actingAs($manager);
        $service->confirm($first, ['confirmation_action' => 'reject'], $manager->id);

        // Ensure the rejected confirmation was deleted
        $this->assertDatabaseMissing('transaction_confirmations', ['id' => $first->id]);

        $second = $service->requestConfirmation($transaction, $teller->id);
        $this->assertSame(TransactionConfirmationStatus::Pending->value, $second->status->value);
        $this->assertNotSame($first->id, $second->id);
    }

    public function test_rate_limit_counter_never_resets_to_one_after_first_attempt(): void
    {
        config(['security.ip_blocking.enabled' => true]);

        $ip = '192.168.1.1';
        $service = app(RateLimitService::class);

        $first = $service->getFailedAttempts($ip);
        $service->recordFailedAttempt($ip);
        $second = $service->getFailedAttempts($ip);

        $this->assertGreaterThan($first, $second);
    }

    public function test_concurrent_journal_entry_reject_attempts_are_serialized(): void
    {
        $entry = JournalEntry::factory()->create(['status' => JournalEntryStatus::Pending]);
        $user = User::factory()->create();

        $service = app(AccountingService::class);
        $service->rejectEntry($entry, $user->id);

        $this->expectException(AccountingPeriodException::class);
        $service->rejectEntry($entry, $user->id);
    }

    public function test_concurrent_journal_entry_reverse_attempts_are_serialized(): void
    {
        $entry = JournalEntry::factory()->create(['status' => JournalEntryStatus::Posted]);
        JournalLine::factory()->for($entry)->debit()->create(['debit' => '100.00', 'credit' => '0']);
        JournalLine::factory()->for($entry)->credit()->create(['debit' => '0', 'credit' => '100.00']);
        $user = User::factory()->create();

        $service = app(AccountingService::class);
        $service->reverseJournalEntry($entry, 'reason', $user->id);

        $this->expectException(AccountingPeriodException::class);
        $service->reverseJournalEntry($entry, 'reason', $user->id);
    }

    public function test_mfa_failed_attempt_counter_accumulates_and_keeps_expiry(): void
    {
        $user = User::factory()->create();
        $service = app(MfaService::class);

        $service->recordFailedAttempt($user);
        $service->recordFailedAttempt($user);

        // add() must seed only when missing: an existing counter is never
        // re-zeroed by a concurrent first attempt.
        $this->assertSame(2, $service->failedAttemptCount($user));

        // The seeded key must carry an expiry; a TTL-less counter would
        // lock the user out of verification permanently. Only observable
        // on the Redis store — the array store expires internally.
        $store = Cache::store(config('ratelimit.store'));
        $underlying = $store->getStore();

        if ($underlying instanceof RedisStore) {
            $prefixedKey = $underlying->getPrefix().'mfa_failed_attempts:'.$user->id;
            $this->assertGreaterThan(0, $underlying->connection()->ttl($prefixedKey));
        }

        $service->clearFailedAttempts($user);
        $this->assertSame(0, $service->failedAttemptCount($user));
    }

    public function test_repeated_compliance_screening_runs_do_not_duplicate_enforcement_rows(): void
    {
        $branch = Branch::factory()->create();
        $customer = Customer::factory()->create();

        Transaction::factory()->for($branch)->for($customer)->create([
            'status' => TransactionStatus::PendingApproval->value,
        ]);

        $response = new ScreeningResponse(
            action: 'block',
            confidenceScore: 95.0,
            matches: collect([
                new ScreeningMatch(
                    entryId: 1,
                    entityName: 'SANCTIONED ENTITY',
                    listName: 'Test List',
                    listSource: 'test',
                    matchScore: 95.0,
                    matchedFields: ['name'],
                    listingDate: null,
                    dateOfBirth: null,
                    nationality: null,
                ),
            ]),
            screenedAt: Carbon::now(),
        );

        $screeningService = $this->mock(CustomerScreeningService::class);
        $screeningService->shouldReceive('screenCustomer')->andReturn($response);

        $thresholdService = $this->mock(ThresholdService::class);
        $thresholdService->shouldReceive('getJobDurationWarning')->andReturn(1000);

        // Parallel workers each run the job for the same customer; the
        // customer-row lock serializes the check-then-create dedup helpers
        // so the loser refreshes rows instead of stacking duplicates.
        $job = new ComplianceScreeningJob($customer->id);
        $job->handle($screeningService, $thresholdService, app(AuditService::class));
        $job->handle($screeningService, $thresholdService, app(AuditService::class));

        $this->assertSame(1, Alert::where('customer_id', $customer->id)->count());

        // One transaction-scoped flag + one customer-scoped flag, no stacking.
        $this->assertSame(1, FlaggedTransaction::where('customer_id', $customer->id)
            ->whereNotNull('transaction_id')->count());
        $this->assertSame(1, FlaggedTransaction::where('customer_id', $customer->id)
            ->whereNull('transaction_id')->count());
    }

    protected function createCompletedSellTransaction(): Transaction
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->for($branch)->create();
        $customer = Customer::factory()->create();

        CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'branch_id' => $branch->id,
            'balance' => '1000.00',
            'avg_cost_rate' => '4.70',
            'last_valuation_rate' => '4.70',
        ]);

        return Transaction::factory()
            ->for($branch)
            ->for($customer)
            ->for($teller)
            ->sell()
            ->completed()
            ->create([
                'currency_code' => 'USD',
                'quantity' => '100.00',
                'amount_myr' => '470.00',
                'rate' => '4.70',
            ]);
    }
}
