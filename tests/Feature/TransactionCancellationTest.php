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

    public function test_cancel_calls_request_cancellation()
    {
        $transaction = Transaction::factory()->create(['status' => TransactionStatus::Completed]);

        $cancellationService = \Mockery::mock(TransactionCancellationService::class);
        $cancellationService->shouldReceive('isWithinCancellationWindow')
            ->once()
            ->with(\Mockery::on(function ($t) use ($transaction) {
                return $t->id === $transaction->id;
            }))
            ->andReturn(true);
        $cancellationService->shouldReceive('requestCancellation')
            ->once()
            ->with(
                \Mockery::on(function ($t) use ($transaction) {
                    return $t->id === $transaction->id;
                }),
                \Mockery::on(function ($u) {
                    return $u instanceof User;
                }),
                'Test cancellation reason'
            )
            ->andReturn(true);

        $this->app->instance(TransactionCancellationService::class, $cancellationService);

        $user = User::factory()->create(['role' => UserRole::Manager]);
        $response = $this->actingAs($user)
            ->post("/transactions/{$transaction->id}/cancel", [
                'cancellation_reason' => 'Test cancellation reason',
                'confirm_understanding' => true,
            ]);

        $response->assertRedirect();
    }

    public function test_direct_cancel_throws_exception()
    {
        $transaction = Transaction::factory()->create(['status' => TransactionStatus::Completed]);

        $user = User::factory()->create(['role' => UserRole::Manager]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Direct cancellation is not allowed');

        $cancellationService = app(TransactionCancellationService::class);
        $cancellationService->cancelTransaction($transaction, $user->id, 'Test reason');
    }
}
