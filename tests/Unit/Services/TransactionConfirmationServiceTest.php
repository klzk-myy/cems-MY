<?php

namespace Tests\Unit\Services;

use App\Enums\TransactionConfirmationStatus;
use App\Models\Transaction;
use App\Models\TransactionConfirmation;
use App\Models\User;
use App\Services\AuditService;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use App\Services\Transaction\TransactionConfirmationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionConfirmationServiceTest extends TestCase
{
    use RefreshDatabase;

    private TransactionConfirmationService $service;

    private AuditService $auditService;

    private ThresholdService $thresholdService;

    private MathService $mathService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auditService = $this->mock(AuditService::class);
        $this->thresholdService = $this->mock(ThresholdService::class);
        $this->mathService = $this->mock(MathService::class);
        $this->service = new TransactionConfirmationService(
            $this->auditService,
            $this->thresholdService,
            $this->mathService
        );
    }

    #[Test]
    public function test_requires_confirmation_returns_true_when_amount_exceeds_threshold(): void
    {
        $transaction = Transaction::factory()->create(['amount_local' => '75000']);
        $this->thresholdService->shouldReceive('getStrThreshold')->once()->andReturn('50000');
        $this->mathService->shouldReceive('compare')->with('75000', '50000')->andReturn(1);

        $result = $this->service->requiresConfirmation($transaction);

        $this->assertTrue($result);
    }

    #[Test]
    public function test_requires_confirmation_returns_false_when_amount_below_threshold(): void
    {
        $transaction = Transaction::factory()->create(['amount_local' => '25000']);
        $this->thresholdService->shouldReceive('getStrThreshold')->once()->andReturn('50000');
        $this->mathService->shouldReceive('compare')->with('25000', '50000')->andReturn(-1);

        $result = $this->service->requiresConfirmation($transaction);

        $this->assertFalse($result);
    }

    #[Test]
    public function test_request_confirmation_creates_new_pending_confirmation(): void
    {
        $user = $this->createUser();
        $transaction = Transaction::factory()->create();

        $this->auditService->shouldReceive('logWithSeverity')
            ->once()
            ->with(
                'confirmation_requested',
                \Mockery::on(function ($data) use ($user, $transaction) {
                    return $data['user_id'] === $user->id &&
                           $data['entity_id'] === $transaction->id &&
                           isset($data['new_values']['confirmation_id']);
                }),
                'INFO'
            );

        $confirmation = $this->service->requestConfirmation($transaction, $user->id);

        $this->assertInstanceOf(TransactionConfirmation::class, $confirmation);
        $this->assertEquals($transaction->id, $confirmation->transaction_id);
        $this->assertEquals($user->id, $confirmation->user_id);
        $this->assertEquals(TransactionConfirmationStatus::Pending, $confirmation->status);
        $this->assertNotNull($confirmation->confirmation_token);
        $this->assertNotNull($confirmation->expires_at);
    }

    #[Test]
    public function test_request_confirmation_returns_existing_pending_confirmation(): void
    {
        $user = $this->createUser();
        $transaction = Transaction::factory()->create();
        $existing = TransactionConfirmation::factory()->create([
            'transaction_id' => $transaction->id,
            'status' => TransactionConfirmationStatus::Pending->value,
        ]);

        $confirmation = $this->service->requestConfirmation($transaction, $user->id);

        $this->assertEquals($existing->id, $confirmation->id);
    }

    #[Test]
    public function test_request_confirmation_returns_existing_confirmed_confirmation(): void
    {
        $user = $this->createUser();
        $transaction = Transaction::factory()->create();
        $existing = TransactionConfirmation::factory()->create([
            'transaction_id' => $transaction->id,
            'status' => TransactionConfirmationStatus::Confirmed->value,
        ]);

        $confirmation = $this->service->requestConfirmation($transaction, $user->id);

        $this->assertEquals($existing->id, $confirmation->id);
    }

    #[Test]
    public function test_request_confirmation_audit_log_is_created(): void
    {
        $user = $this->createUser();
        $transaction = Transaction::factory()->create();

        $this->auditService->shouldReceive('logWithSeverity')
            ->once()
            ->with(
                'confirmation_requested',
                \Mockery::on(function ($data) use ($user, $transaction) {
                    return $data['user_id'] === $user->id &&
                           $data['entity_type'] === 'Transaction' &&
                           $data['entity_id'] === $transaction->id &&
                           isset($data['new_values']['confirmation_id']) &&
                           isset($data['new_values']['amount_local']);
                }),
                'INFO'
            );

        $this->service->requestConfirmation($transaction, $user->id);
    }

    #[Test]
    public function test_confirm_transaction_calls_audit_service(): void
    {
        $confirmation = TransactionConfirmation::factory()->create([
            'status' => TransactionConfirmationStatus::Pending->value,
        ]);
        $user = $this->createUser();

        $this->auditService->shouldReceive('logWithSeverity')
            ->once()
            ->with(
                'transaction_confirmed',
                \Mockery::on(function ($data) use ($user, $confirmation) {
                    return $data['user_id'] === $user->id &&
                           $data['entity_id'] === $confirmation->transaction_id &&
                           $data['new_values']['confirmed_by'] === $user->id;
                }),
                'INFO'
            );

        $validated = ['confirmation_action' => 'confirm', 'notes' => 'Test'];
        $result = $this->service->confirm($confirmation, $validated, $user->id);

        $this->assertTrue($result['success']);
        $this->assertEquals('Transaction confirmed and pending final approval.', $result['message']);
    }

    // Helper to create a user
    private function createUser()
    {
        return User::factory()->create();
    }
}
