<?php

namespace Tests\Feature;

use App\Enums\StockTransferStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StockTransferBranchIsolationTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function admin_is_allowed_every_action_via_policy(): void
    {
        [$branchA, $branchB, $admin] = $this->makeBranchesAndAdmin();
        $otherAdmin = User::factory()->create(['role' => UserRole::Admin]);

        $transfer = $this->makeTransfer($branchA->name, $branchB->name, $otherAdmin->id);

        $this->assertTrue(Gate::forUser($admin)->allows('view', $transfer));
        $this->assertTrue(Gate::forUser($admin)->allows('approveBranchManager', $transfer));
        $this->assertTrue(Gate::forUser($admin)->allows('dispatch', $transfer));
        $this->assertTrue(Gate::forUser($admin)->allows('cancel', $transfer));
        $this->assertTrue(Gate::forUser($admin)->allows('receive', $transfer));
        $this->assertTrue(Gate::forUser($admin)->allows('complete', $transfer));
        $this->assertTrue(Gate::forUser($admin)->allows('reject', $transfer));
    }

    #[Test]
    public function source_branch_manager_is_allowed_source_actions_but_not_destination_only(): void
    {
        [$branchA, $branchB, $admin] = $this->makeBranchesAndAdmin();
        $managerA = User::factory()->create(['role' => UserRole::Manager, 'branch_id' => $branchA->id]);

        $transfer = $this->makeTransfer($branchA->name, $branchB->name, $managerA->id);

        $this->assertTrue(Gate::forUser($managerA)->allows('view', $transfer));
        $this->assertTrue(Gate::forUser($managerA)->allows('dispatch', $transfer));
        $this->assertTrue(Gate::forUser($managerA)->allows('cancel', $transfer));

        // Maker/taker: the SOURCE manager (maker) cannot approve — approval
        // belongs to the destination branch, and self-approval is denied anyway.
        $this->assertFalse(Gate::forUser($managerA)->allows('approveBranchManager', $transfer));

        // Source manager is not a member of the destination branch.
        $this->assertFalse(Gate::forUser($managerA)->allows('receive', $transfer));
        $this->assertFalse(Gate::forUser($managerA)->allows('complete', $transfer));
        $this->assertFalse(Gate::forUser($managerA)->allows('reject', $transfer));
    }

    #[Test]
    public function destination_branch_manager_is_allowed_destination_actions_but_not_source_only(): void
    {
        [$branchA, $branchB, $admin] = $this->makeBranchesAndAdmin();
        $managerB = User::factory()->create(['role' => UserRole::Manager, 'branch_id' => $branchB->id]);

        // The transfer is requested by the source branch, so the destination
        // manager is the taker who approves it.
        $transfer = $this->makeTransfer($branchA->name, $branchB->name, $admin->id);

        $this->assertTrue(Gate::forUser($managerB)->allows('view', $transfer));
        $this->assertTrue(Gate::forUser($managerB)->allows('approveBranchManager', $transfer));
        $this->assertTrue(Gate::forUser($managerB)->allows('receive', $transfer));
        $this->assertTrue(Gate::forUser($managerB)->allows('complete', $transfer));
        $this->assertTrue(Gate::forUser($managerB)->allows('reject', $transfer));

        // Destination manager is not the source branch manager.
        $this->assertFalse(Gate::forUser($managerB)->allows('dispatch', $transfer));
        $this->assertFalse(Gate::forUser($managerB)->allows('cancel', $transfer));
    }

    #[Test]
    public function taker_cannot_approve_transfer_they_requested(): void
    {
        [$branchA, $branchB, $admin] = $this->makeBranchesAndAdmin();
        $managerB = User::factory()->create(['role' => UserRole::Manager, 'branch_id' => $branchB->id]);

        // Self-approval: the taker cannot approve a transfer they requested.
        $transfer = $this->makeTransfer($branchA->name, $branchB->name, $managerB->id);

        $this->assertFalse(Gate::forUser($managerB)->allows('approveBranchManager', $transfer));
    }

    #[Test]
    public function unrelated_branch_manager_is_denied_every_action(): void
    {
        [$branchA, $branchB, $admin] = $this->makeBranchesAndAdmin();
        $branchC = Branch::factory()->create();
        $managerC = User::factory()->create(['role' => UserRole::Manager, 'branch_id' => $branchC->id]);

        $transfer = $this->makeTransfer($branchA->name, $branchB->name, $managerC->id);

        foreach (['view', 'approveBranchManager', 'dispatch', 'cancel', 'receive', 'complete'] as $ability) {
            $this->assertFalse(
                Gate::forUser($managerC)->allows($ability, $transfer),
                "Manager from unrelated branch should be denied {$ability}"
            );
        }
    }

    #[Test]
    public function controller_routes_enforce_isolation_via_403(): void
    {
        [$branchA, $branchB, $admin] = $this->makeBranchesAndAdmin();
        $branchC = Branch::factory()->create();
        $managerC = User::factory()->create(['role' => UserRole::Manager, 'branch_id' => $branchC->id]);
        $managerA = User::factory()->create(['role' => UserRole::Manager, 'branch_id' => $branchA->id]);
        $managerB = User::factory()->create(['role' => UserRole::Manager, 'branch_id' => $branchB->id]);

        $transfer = $this->makeTransfer($branchA->name, $branchB->name, $managerA->id);

        // Unrelated manager is blocked at the route (before any view render).
        $this->actingAs($managerC)
            ->get(route('stock-transfers.show', $transfer))
            ->assertForbidden();

        $this->actingAs($managerC)
            ->post(route('stock-transfers.approve-bm', $transfer))
            ->assertForbidden();

        $this->actingAs($managerC)
            ->post(route('stock-transfers.cancel', $transfer), ['reason' => 'x'])
            ->assertForbidden();

        // Source manager (maker) cannot approve their own transfer.
        $this->actingAs($managerA)
            ->post(route('stock-transfers.approve-bm', $transfer))
            ->assertForbidden();

        // Destination manager (taker) approves — POST redirects, never 403.
        $allowed = $this->actingAs($managerB)
            ->post(route('stock-transfers.approve-bm', $transfer));
        $this->assertNotEquals(403, $allowed->getStatusCode());

        $allowedCancel = $this->actingAs($managerA)
            ->post(route('stock-transfers.cancel', $transfer), ['reason' => 'x']);
        $this->assertNotEquals(403, $allowedCancel->getStatusCode());
    }

    #[Test]
    public function maker_cannot_reject_transfer_at_route(): void
    {
        [$branchA, $branchB, $admin] = $this->makeBranchesAndAdmin();
        $managerA = User::factory()->create(['role' => UserRole::Manager, 'branch_id' => $branchA->id]);

        $transfer = $this->makeTransfer($branchA->name, $branchB->name, $managerA->id);

        // The maker cannot reject — rejection belongs to the taker.
        $this->actingAs($managerA)
            ->post(route('stock-transfers.reject', $transfer), ['reason' => 'x'])
            ->assertForbidden();
    }

    /**
     * @return array{Branch, Branch, User}
     */
    private function makeBranchesAndAdmin(): array
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::Admin, 'branch_id' => $branchA->id]);

        return [$branchA, $branchB, $admin];
    }

    private function makeTransfer(string $source, string $destination, int $requesterId): StockTransfer
    {
        return StockTransfer::create([
            'transfer_number' => 'TRF-'.now()->format('Ymd').'-'.uniqid('', true),
            'type' => 'Standard',
            'status' => StockTransferStatus::Requested->value,
            'source_branch_name' => $source,
            'destination_branch_name' => $destination,
            'requested_by' => $requesterId,
        ]);
    }
}
