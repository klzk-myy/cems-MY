<?php

namespace Tests;

use App\Enums\ApprovalStatus;
use App\Enums\CounterSessionStatus;
use App\Enums\TellerAllocationStatus;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\CounterSession;
use App\Models\Customer;
use App\Models\PepApprovalRequest;
use App\Models\TellerAllocation;
use App\Models\TillBalance;
use App\Models\User;
use Database\Seeders\AccountingPeriodSeeder;
use Database\Seeders\SchemaSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\PendingCommand;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['cems.mfa.enabled' => false]);

        // Reset shared cache state between tests. StrictRateLimit uses
        // Laravel's built-in RateLimiter, which stores hit counters in the
        // DEFAULT cache store (not config('ratelimit.store')), so the
        // RATE_LIMIT_CACHE_STORE=array env alone does NOT isolate it. The
        // array store persists across tests within one PHPUnit process;
        // without this flush the full suite 429s all API tests after ~60
        // requests from the shared 127.0.0.1 test IP. Keep this flush even if
        // the env vars look redundant.
        Cache::flush();
    }

    /**
     * Boot the testing helper traits.
     *
     * Override to ensure the in-memory database schema exists before traits
     * like DatabaseTransactions begin their transaction lifecycle.
     */
    protected function setUpTraits(): array
    {
        $this->ensureInMemoryDatabaseReady();

        return parent::setUpTraits();
    }

    /**
     * For in-memory SQLite, ensure the schema exists and the PDO is preserved
     * between test classes. DatabaseTransactions has no migration logic and
     * no in-memory connection preservation, so we handle it here.
     *
     * Only applies to tests using a DB trait — tests with no DB trait are
     * unaffected.
     */
    protected function ensureInMemoryDatabaseReady(): void
    {
        $uses = array_flip(class_uses_recursive(static::class));

        $isDatabaseTest = isset($uses[DatabaseTransactions::class])
            || isset($uses[RefreshDatabase::class]);

        if (! $isDatabaseTest) {
            return;
        }

        $default = config('database.default');
        $isInMemory = config("database.connections.{$default}.database") === ':memory:';

        $connection = $this->app->make('db')->connection();
        $connectionName = $connection->getName();

        if ($isInMemory) {
            if (isset(RefreshDatabaseState::$inMemoryConnections[$connectionName])) {
                $connection->setPdo(RefreshDatabaseState::$inMemoryConnections[$connectionName]);
            } else {
                // Schema is provided by SchemaSeeder (the migration-free source of
                // truth) rather than migrate:fresh, because the migrations
                // directory has been retired.
                SchemaSeeder::seedNow($this->app);

                // Seed the current/previous/next month accounting periods so
                // journal entries posted at now()->toDateString() always have
                // an open period to link to (spec.md §4.1 requires every
                // journal entry to be linked to an AccountingPeriod).
                $this->app->make(AccountingPeriodSeeder::class)->run();

                $this->app[Kernel::class]->setArtisan(null);
                RefreshDatabaseState::$inMemoryConnections[$connectionName] = $connection->getPdo();

                // Without this a later RefreshDatabase class re-runs the schema
                // seed against the shared PDO instead of reusing the seeded schema.
                RefreshDatabaseState::$migrated = true;
            }
        }

        // A test that opens a transaction and never finishes it leaves the
        // in-memory PDO transaction open. Because that PDO is shared across
        // test classes, the next beginDatabaseTransaction() throws "There is
        // already an active transaction" and cascades a failure into every
        // remaining test in the process. Discard the leaked transaction so one
        // badly-behaved class cannot take down the rest of the suite.
        if ($connection->getPdo()->inTransaction()) {
            $connection->getPdo()->rollBack();
        }
    }

    /**
     * Run an artisan command returning the pending-command expectation builder.
     *
     * @param  array<string, mixed>  $parameters
     */
    protected function artisanCommand(string $command, array $parameters = []): PendingCommand
    {
        $pending = $this->artisan($command, $parameters);
        assert($pending instanceof PendingCommand);

        return $pending;
    }

    /**
     * Get a test user with the specified role.
     */
    protected function getTestUser(string $role = 'teller'): User
    {
        return User::where('role', $role)->first();
    }

    /**
     * Create a test branch.
     */
    protected function createTestBranch(array $attributes = []): Branch
    {
        return Branch::create(array_merge([
            'code' => 'TEST-'.uniqid(),
            'name' => 'Test Branch',
            'address' => '123 Test Street',
            'phone' => '+60312345678',
            'email' => 'test@localhost.com',
            'is_active' => true,
        ], $attributes));
    }

    /**
     * Create a test counter.
     */
    protected function createTestCounter(array $attributes = []): Counter
    {
        return Counter::create(array_merge([
            'name' => 'Test Counter',
            'code' => substr(uniqid(), -8),
            'is_active' => true,
        ], $attributes));
    }

    /**
     * Create a test customer.
     */
    protected function createTestCustomer(array $attributes = []): Customer
    {
        /** @var Customer $customer */
        $customer = Customer::factory()->create(array_merge([
            'full_name' => 'Test Customer',
            'id_type' => 'MyKad',
            'id_number_encrypted' => encrypt('123456789012'),
            'nationality' => 'MY',
            'date_of_birth' => '1990-01-01',
            'risk_rating' => 'Low',
            'risk_score' => 10,
            'cdd_level' => 'Simplified',
            'sanction_hit' => false,
            'is_active' => true,
        ], $attributes));

        return $customer;
    }

    /**
     * Set up an open till for a user and currency.
     */
    protected function setupOpenTill(User $user, string $currencyCode = 'USD', string $openingBalance = '1000.00'): Counter
    {
        $branch = $this->createTestBranch();
        $counter = $this->createTestCounter(['branch_id' => $branch->id]);

        // ValidTill scopes counters to the acting user's branch — the user
        // operating this till must belong to the same branch.
        if ($user->branch_id !== $branch->id) {
            $user->forceFill(['branch_id' => $branch->id])->save();
        }

        CounterSession::create([
            'counter_id' => $counter->id,
            'user_id' => $user->id,
            'session_date' => now()->toDateString(),
            'opened_at' => now(),
            'opened_by' => $user->id,
            'status' => CounterSessionStatus::Open,
        ]);

        TillBalance::create([
            'till_id' => (string) $counter->code,
            'currency_code' => $currencyCode,
            'branch_id' => $branch->id,
            'opening_balance' => $openingBalance,
            'date' => now()->toDateString(),
            'opened_by' => $user->id,
        ]);

        // Also create MYR till balance (required by TransactionService::updateTillBalance)
        TillBalance::create([
            'till_id' => (string) $counter->code,
            'currency_code' => 'MYR',
            'branch_id' => $branch->id,
            'opening_balance' => '100000.00',
            'date' => now()->toDateString(),
            'opened_by' => $user->id,
        ]);

        // Create active teller allocation (required by TransactionService for Buy transactions)
        TellerAllocation::create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'counter_id' => $counter->id,
            'currency_code' => $currencyCode,
            'allocated_amount' => $openingBalance,
            'current_balance' => $openingBalance,
            'requested_amount' => $openingBalance,
            'daily_limit_myr' => '500000.0000',
            'daily_used_myr' => '0.0000',
            'status' => TellerAllocationStatus::Active,
            'session_date' => now()->toDateString(),
        ]);

        return $counter;
    }

    /**
     * Set MFA verification session values for a user (required for web route transactions).
     */
    protected function setMfaVerification(User $user): void
    {
        session(['mfa_verified' => true, 'mfa_verified_at' => now()->timestamp]);
    }

    /**
     * Session payload marking the request as freshly password-confirmed,
     * satisfying the password.confirm middleware on destructive routes.
     *
     * @return array<string, int>
     */
    protected function passwordConfirmedSession(): array
    {
        return ['auth.password_confirmed_at' => time()];
    }

    /**
     * Record an approved PEP sign-off for a customer so transaction
     * creation passes the head-office approval gate.
     */
    protected function approvePepFor(Customer $customer): void
    {
        PepApprovalRequest::factory()->create([
            'customer_id' => $customer->id,
            'status' => ApprovalStatus::Approved->value,
            'approved_by' => User::factory(),
            'approved_at' => now(),
        ]);
    }
}
