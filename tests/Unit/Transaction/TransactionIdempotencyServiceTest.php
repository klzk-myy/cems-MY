<?php

namespace Tests\Unit\Transaction;

use App\Models\Transaction;
use App\Models\User;
use App\Services\Transaction\TransactionIdempotencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionIdempotencyServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TransactionIdempotencyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TransactionIdempotencyService;
    }

    #[Test]
    public function find_duplicate_by_idempotency_key_returns_existing_transaction(): void
    {
        $user = User::factory()->create();
        $existing = Transaction::factory()->create([
            'idempotency_key' => 'test-key-123',
            'user_id' => $user->id,
        ]);

        $result = $this->service->findDuplicate('test-key-123', $user->id, []);

        $this->assertNotNull($result);
        $this->assertEquals($existing->id, $result->id);
        $this->assertEquals($existing->idempotency_key, $result->idempotency_key);
    }

    #[Test]
    public function find_duplicate_returns_null_when_no_match(): void
    {
        $user = User::factory()->create();

        $result = $this->service->findDuplicate('non-existent-key', $user->id, []);

        $this->assertNull($result);
    }

    #[Test]
    public function find_duplicate_returns_null_with_empty_key(): void
    {
        $user = User::factory()->create();

        $result = $this->service->findDuplicate(null, $user->id, []);

        $this->assertNull($result);
    }

    #[Test]
    public function check_recent_duplicate_finds_transaction_within_window(): void
    {
        $user = User::factory()->create();
        $now = now();

        // Create a transaction 10 seconds ago
        Transaction::factory()->create([
            'user_id' => $user->id,
            'currency_code' => 'USD',
            'type' => 'Buy',
            'amount_foreign' => '1000.00',
            'created_at' => $now->copy()->subSeconds(10),
        ]);

        $data = [
            'currency_code' => 'USD',
            'type' => 'Buy',
            'amount_foreign' => '1000.00',
        ];

        $result = $this->service->checkRecentDuplicate($user->id, $data, 30);

        $this->assertNotNull($result);
        $this->assertEquals('USD', $result->currency_code);
    }

    #[Test]
    public function check_recent_duplicate_returns_null_outside_window(): void
    {
        $user = User::factory()->create();

        // Create a transaction 60 seconds ago (outside 30s default window)
        Transaction::factory()->create([
            'user_id' => $user->id,
            'currency_code' => 'USD',
            'type' => 'Buy',
            'amount_foreign' => '1000.00',
            'created_at' => now()->subSeconds(60),
        ]);

        $data = [
            'currency_code' => 'USD',
            'type' => 'Buy',
            'amount_foreign' => '1000.00',
        ];

        $result = $this->service->checkRecentDuplicate($user->id, $data, 30);

        $this->assertNull($result);
    }

    #[Test]
    public function check_recent_duplicate_considers_different_amount_not_duplicate(): void
    {
        $user = User::factory()->create();

        Transaction::factory()->create([
            'user_id' => $user->id,
            'currency_code' => 'USD',
            'type' => 'Buy',
            'amount_foreign' => '1000.00',
            'created_at' => now()->subSeconds(5),
        ]);

        $data = [
            'currency_code' => 'USD',
            'type' => 'Buy',
            'amount_foreign' => '2000.00', // different amount
        ];

        $result = $this->service->checkRecentDuplicate($user->id, $data, 30);

        $this->assertNull($result);
    }

    #[Test]
    public function check_recent_duplicate_considers_different_currency_not_duplicate(): void
    {
        $user = User::factory()->create();

        Transaction::factory()->create([
            'user_id' => $user->id,
            'currency_code' => 'EUR',
            'type' => 'Buy',
            'amount_foreign' => '1000.00',
            'created_at' => now()->subSeconds(5),
        ]);

        $data = [
            'currency_code' => 'USD',
            'type' => 'Buy',
            'amount_foreign' => '1000.00',
        ];

        $result = $this->service->checkRecentDuplicate($user->id, $data, 30);

        $this->assertNull($result);
    }

    #[Test]
    public function check_recent_duplicate_considers_different_type_not_duplicate(): void
    {
        $user = User::factory()->create();

        Transaction::factory()->create([
            'user_id' => $user->id,
            'currency_code' => 'USD',
            'type' => 'Sell', // different type
            'amount_foreign' => '1000.00',
            'created_at' => now()->subSeconds(5),
        ]);

        $data = [
            'currency_code' => 'USD',
            'type' => 'Buy',
            'amount_foreign' => '1000.00',
        ];

        $result = $this->service->checkRecentDuplicate($user->id, $data, 30);

        $this->assertNull($result);
    }
}
