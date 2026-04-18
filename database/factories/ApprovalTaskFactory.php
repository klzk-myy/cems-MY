<?php

namespace Database\Factories;

use App\Models\ApprovalTask;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApprovalTask>
 */
class ApprovalTaskFactory extends Factory
{
    protected $model = ApprovalTask::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transaction_id' => Transaction::factory(),
            'approver_id' => null,
            'status' => ApprovalTask::STATUS_PENDING,
            'threshold_amount' => '3000.0000',
            'required_role' => 'supervisor',
            'notes' => null,
            'expires_at' => now()->addHours(ApprovalTaskFactory::DEFAULT_EXPIRATION_HOURS),
            'decided_at' => null,
        ];
    }

    public const DEFAULT_EXPIRATION_HOURS = 24;

    /**
     * Indicate that the task is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ApprovalTask::STATUS_PENDING,
            'approver_id' => null,
            'decided_at' => null,
        ]);
    }

    /**
     * Indicate that the task has been approved.
     */
    public function approved(?User $approver = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ApprovalTask::STATUS_APPROVED,
            'approver_id' => $approver?->id ?? User::factory()->create()->id,
            'decided_at' => now(),
        ]);
    }

    /**
     * Indicate that the task has been rejected.
     */
    public function rejected(?User $approver = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ApprovalTask::STATUS_REJECTED,
            'approver_id' => $approver?->id ?? User::factory()->create()->id,
            'decided_at' => now(),
            'notes' => 'Rejected: '.fake()->sentence(),
        ]);
    }

    /**
     * Indicate that the task has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ApprovalTask::STATUS_EXPIRED,
            'decided_at' => now(),
        ]);
    }

    /**
     * Indicate that the task requires supervisor approval.
     */
    public function supervisorRequired(): static
    {
        return $this->state(fn (array $attributes) => [
            'threshold_amount' => '3000.0000',
            'required_role' => 'supervisor',
        ]);
    }

    /**
     * Indicate that the task requires manager approval.
     */
    public function managerRequired(): static
    {
        return $this->state(fn (array $attributes) => [
            'threshold_amount' => '10000.0000',
            'required_role' => 'manager',
        ]);
    }

    /**
     * Indicate that the task requires admin approval.
     */
    public function adminRequired(): static
    {
        return $this->state(fn (array $attributes) => [
            'threshold_amount' => '50000.0000',
            'required_role' => 'admin',
        ]);
    }

    /**
     * Create a task that has already expired.
     */
    public function stale(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subHours(1),
        ]);
    }

    /**
     * Create a task for a specific transaction.
     */
    public function forTransaction(Transaction $transaction): static
    {
        return $this->state(fn (array $attributes) => [
            'transaction_id' => $transaction->id,
        ]);
    }
}
