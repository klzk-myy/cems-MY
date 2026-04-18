<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Get a test user with the specified role.
     */
    protected function getTestUser(string $role = 'teller'): \App\Models\User
    {
        return \App\Models\User::where('role', $role)->first();
    }

    /**
     * Create a test branch.
     */
    protected function createTestBranch(array $attributes = []): \App\Models\Branch
    {
        return \App\Models\Branch::create(array_merge([
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
    protected function createTestCounter(array $attributes = []): \App\Models\Counter
    {
        return \App\Models\Counter::create(array_merge([
            'name' => 'Test Counter',
            'code' => substr(uniqid(), -8),
            'is_active' => true,
        ], $attributes));
    }

    /**
     * Create a test customer.
     */
    protected function createTestCustomer(array $attributes = []): \App\Models\Customer
    {
        return \App\Models\Customer::create(array_merge([
            'full_name' => 'Test Customer',
            'id_type' => 'MyKad',
            'id_number_encrypted' => encrypt('123456789012'),
            'nationality' => 'MY',
            'date_of_birth' => '1990-01-01',
            'risk_rating' => 'Low',
            'cdd_level' => 'Simplified',
            'is_active' => true,
        ], $attributes));
    }

    /**
     * Set up an open till for a user and currency.
     */
    protected function setupOpenTill(\App\Models\User $user, string $currencyCode = 'USD', string $openingBalance = '1000.00'): \App\Models\Counter
    {
        $branch = $this->createTestBranch();
        $counter = $this->createTestCounter(['branch_id' => $branch->id]);

        \App\Models\CounterSession::create([
            'counter_id' => $counter->id,
            'user_id' => $user->id,
            'session_date' => now()->toDateString(),
            'opened_at' => now(),
            'opened_by' => $user->id,
            'status' => \App\Enums\CounterSessionStatus::Open,
        ]);

        \App\Models\TillBalance::create([
            'till_id' => (string) $counter->id,
            'currency_code' => $currencyCode,
            'branch_id' => $branch->id,
            'opening_balance' => $openingBalance,
            'date' => now()->toDateString(),
            'opened_by' => $user->id,
        ]);

        // Also create MYR till balance (required by TransactionService::updateTillBalance)
        \App\Models\TillBalance::create([
            'till_id' => (string) $counter->id,
            'currency_code' => 'MYR',
            'branch_id' => $branch->id,
            'opening_balance' => '100000.00',
            'date' => now()->toDateString(),
            'opened_by' => $user->id,
        ]);

        // Create active teller allocation (required by TransactionService for Buy transactions)
        \App\Models\TellerAllocation::create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'counter_id' => $counter->id,
            'currency_code' => $currencyCode,
            'allocated_amount' => $openingBalance,
            'current_balance' => $openingBalance,
            'requested_amount' => $openingBalance,
            'daily_limit_myr' => '500000.0000',
            'daily_used_myr' => '0.0000',
            'status' => \App\Enums\TellerAllocationStatus::ACTIVE,
            'session_date' => now()->toDateString(),
        ]);

        return $counter;
    }

    /**
     * Set MFA verification session values for a user (required for web route transactions).
     */
    protected function setMfaVerification(\App\Models\User $user): void
    {
        session(['mfa_verified' => true, 'mfa_verified_at' => now()->timestamp]);
    }
}
