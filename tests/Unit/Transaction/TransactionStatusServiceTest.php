<?php

namespace Tests\Unit\Transaction;

use App\Models\Transaction;
use App\Models\User;
use App\Services\Transaction\TransactionStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TransactionStatusService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TransactionStatusService;
    }

    #[Test]
    public function completed_transaction_is_refundable_within_window(): void
    {
        $user = User::factory()->create();
        $transaction = Transaction::factory()->create([
            'status' => 'Completed',
            'user_id' => $user->id,
            'created_at' => now()->subHours(12), // 12 hours ago
            'is_refund' => false,
            'cancelled_at' => null,
        ]);

        $this->assertTrue($this->service->isRefundable($transaction));
    }

    #[Test]
    public function completed_transaction_not_refundable_after_window(): void
    {
        $user = User::factory()->create();
        $transaction = Transaction::factory()->create([
            'status' => 'Completed',
            'user_id' => $user->id,
            'created_at' => now()->subHours(48), // 48 hours ago
            'is_refund' => false,
            'cancelled_at' => null,
        ]);

        $this->assertFalse($this->service->isRefundable($transaction));
    }

    #[Test]
    public function cancelled_transaction_not_refundable(): void
    {
        $user = User::factory()->create();
        $transaction = Transaction::factory()->create([
            'status' => 'Completed',
            'user_id' => $user->id,
            'created_at' => now()->subHours(12),
            'is_refund' => false,
            'cancelled_at' => now()->subHours(1),
        ]);

        $this->assertFalse($this->service->isRefundable($transaction));
    }

    #[Test]
    public function refund_transaction_not_refundable(): void
    {
        $user = User::factory()->create();
        $transaction = Transaction::factory()->create([
            'status' => 'Completed',
            'user_id' => $user->id,
            'created_at' => now()->subHours(12),
            'is_refund' => true,
            'cancelled_at' => null,
        ]);

        $this->assertFalse($this->service->isRefundable($transaction));
    }

    #[Test]
    public function non_completed_transaction_not_refundable(): void
    {
        $user = User::factory()->create();
        $transaction = Transaction::factory()->create([
            'status' => 'PendingApproval',
            'user_id' => $user->id,
            'created_at' => now()->subHours(1),
            'is_refund' => false,
            'cancelled_at' => null,
        ]);

        $this->assertFalse($this->service->isRefundable($transaction));
    }

    #[Test]
    public function is_cancelled_returns_true_when_cancelled_at_set(): void
    {
        $user = User::factory()->create();
        $transaction = Transaction::factory()->create([
            'cancelled_at' => now(),
        ]);

        $this->assertTrue($this->service->isCancelled($transaction));
    }

    #[Test]
    public function is_cancelled_returns_false_when_cancelled_at_null(): void
    {
        $user = User::factory()->create();
        $transaction = Transaction::factory()->create([
            'cancelled_at' => null,
        ]);

        $this->assertFalse($this->service->isCancelled($transaction));
    }
}
