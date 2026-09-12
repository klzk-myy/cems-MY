<?php

namespace Tests\Feature;

use App\Enums\TransactionStatus;
use App\Jobs\ProcessTransactionRetry;
use App\Models\SystemLog;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DlqAdminPageTest extends TestCase
{
    use RefreshDatabase;

    private function dlqTransaction(): Transaction
    {
        $transaction = Transaction::factory()->create([
            'status' => TransactionStatus::Failed,
            'failure_reason' => '[DLQ] Max retries exceeded',
        ]);

        $transaction->is_dlq = true;
        $transaction->save();

        return $transaction;
    }

    #[Test]
    public function admin_can_view_dead_letter_queue_page(): void
    {
        $admin = User::factory()->admin()->create();
        $transaction = $this->dlqTransaction();

        $this->actingAs($admin)
            ->get(route('transactions.dlq'))
            ->assertOk()
            ->assertSee($transaction->reference)
            ->assertSee('Max retries exceeded');
    }

    #[Test]
    public function non_admin_cannot_view_dead_letter_queue_page(): void
    {
        $teller = User::factory()->teller()->create();

        $this->actingAs($teller)
            ->get(route('transactions.dlq'))
            ->assertForbidden();
    }

    #[Test]
    public function non_admin_cannot_retry_dlq_transaction(): void
    {
        $manager = User::factory()->manager()->create();
        $transaction = $this->dlqTransaction();

        $this->actingAs($manager)
            ->post(route('transactions.dlq.retry', $transaction))
            ->assertForbidden();

        $this->assertTrue($transaction->refresh()->is_dlq);
    }

    #[Test]
    public function admin_can_retry_dlq_transaction(): void
    {
        Queue::fake();

        $admin = User::factory()->admin()->create();
        $transaction = $this->dlqTransaction();

        $this->actingAs($admin)
            ->post(route('transactions.dlq.retry', $transaction))
            ->assertRedirect()
            ->assertSessionHas('success');

        $transaction->refresh();

        $this->assertFalse($transaction->is_dlq);
        $this->assertSame(TransactionStatus::PendingApproval, $transaction->status);

        // DLQ retry routes through the manual approval flow: the transaction
        // is moved to PendingApproval and no retry job is dispatched (the job
        // skips anything not in Failed status).
        Queue::assertNotPushed(ProcessTransactionRetry::class);
    }

    #[Test]
    public function retry_of_non_dlq_transaction_returns_error(): void
    {
        $admin = User::factory()->admin()->create();
        $transaction = Transaction::factory()->create([
            'status' => TransactionStatus::Failed,
        ]);

        $this->actingAs($admin)
            ->from(route('transactions.dlq'))
            ->post(route('transactions.dlq.retry', $transaction))
            ->assertRedirect(route('transactions.dlq'))
            ->assertSessionHas('error');

        $this->assertFalse($transaction->refresh()->is_dlq);
    }

    #[Test]
    public function admin_can_archive_dlq_transaction(): void
    {
        $admin = User::factory()->admin()->create();
        $transaction = $this->dlqTransaction();

        $this->actingAs($admin)
            ->from(route('transactions.dlq'))
            ->post(route('transactions.dlq.purge', $transaction))
            ->assertRedirect(route('transactions.dlq'))
            ->assertSessionHas('success');

        // Archived = soft-deleted: hidden from the DLQ but retained in the table.
        $this->assertNull(Transaction::find($transaction->id));
        $this->assertNotNull(Transaction::withTrashed()->find($transaction->id));

        // The DLQ page no longer lists it.
        $this->actingAs($admin)
            ->get(route('transactions.dlq'))
            ->assertOk()
            ->assertDontSee($transaction->reference);
    }

    #[Test]
    public function purge_resolves_outstanding_error_records(): void
    {
        $admin = User::factory()->admin()->create();
        $transaction = $this->dlqTransaction();
        $error = $transaction->transactionErrors()->create([
            'error_type' => 'processing_error',
            'error_message' => 'Booking failed',
            'retry_count' => 3,
            'max_retries' => 3,
            'next_retry_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('transactions.dlq.purge', $transaction))
            ->assertRedirect()
            ->assertSessionHas('success');

        $error->refresh();
        $this->assertNotNull($error->resolved_at);
        $this->assertSame('Purged from DLQ - archived', $error->resolution_notes);
    }

    #[Test]
    public function purge_records_audit_trail(): void
    {
        $admin = User::factory()->admin()->create();
        $transaction = $this->dlqTransaction();

        $this->actingAs($admin)
            ->post(route('transactions.dlq.purge', $transaction), ['reason' => 'Duplicate entry'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('system_logs', [
            'action' => 'transaction_dlq_purged',
            'entity_type' => 'Transaction',
            'entity_id' => $transaction->id,
        ]);

        // The purge reason must be persisted (not silently dropped).
        $log = SystemLog::where('action', 'transaction_dlq_purged')
            ->where('entity_id', $transaction->id)
            ->first();
        $this->assertSame('Duplicate entry', $log->new_values['reason'] ?? null);
    }

    #[Test]
    public function non_admin_cannot_archive_dlq_transaction(): void
    {
        $manager = User::factory()->manager()->create();
        $transaction = $this->dlqTransaction();

        $this->actingAs($manager)
            ->post(route('transactions.dlq.purge', $transaction))
            ->assertForbidden();

        $this->assertNotNull(Transaction::find($transaction->id));
    }

    #[Test]
    public function purge_of_non_dlq_transaction_returns_error(): void
    {
        $admin = User::factory()->admin()->create();
        $transaction = Transaction::factory()->create([
            'status' => TransactionStatus::Failed,
        ]);

        $this->actingAs($admin)
            ->from(route('transactions.dlq'))
            ->post(route('transactions.dlq.purge', $transaction))
            ->assertRedirect(route('transactions.dlq'))
            ->assertSessionHas('error');

        $this->assertNotNull(Transaction::find($transaction->id));
    }
}
