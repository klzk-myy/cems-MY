<?php

namespace Tests\Feature;

use App\Enums\TransactionStatus;
use App\Enums\UserRole;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionCancellationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancel_delegates_to_service()
    {
        $transaction = Transaction::factory()->create(['status' => TransactionStatus::Completed]);

        $cancellationService = \Mockery::mock(TransactionCancellationService::class);
        $cancellationService->shouldReceive('cancelTransaction')
            ->once()
            ->with(
                \Mockery::on(function ($t) use ($transaction) {
                    return $t->id === $transaction->id;
                }),
                \Mockery::type('int'),
                'Test cancellation reason'
            )
            ->andReturn([
                'transaction' => $transaction,
                'refund_transaction' => null,
            ]);

        $this->app->instance(TransactionCancellationService::class, $cancellationService);

        $user = User::factory()->create(['role' => UserRole::Manager]);
        $response = $this->actingAs($user)
            ->post("/transactions/{$transaction->id}/cancel", [
                'cancellation_reason' => 'Test cancellation reason',
                'confirm_understanding' => true,
            ]);

        $response->assertRedirect();
    }
}
