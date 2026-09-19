<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Exceptions\Domain\UserManagementException;
use App\Models\Branch;
use App\Models\User;
use App\Services\Customer\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Role-assignment privilege-escalation coverage.
 *
 * A forged request must never let a manager create or promote a user past
 * their assignableRoles() set (tellers only), and the same restriction is
 * enforced at the validation, policy, and service layers.
 */
class UserManagementRoleAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create();
        $this->manager = User::factory()->create([
            'role' => UserRole::Manager,
            'branch_id' => $this->branch->id,
        ]);
    }

    #[Test]
    public function manager_cannot_create_an_admin_via_forged_post(): void
    {
        $response = $this->actingAs($this->manager)->withSession($this->passwordConfirmedSession())->post(route('users.store'), [
            'username' => 'escalated',
            'email' => 'escalated@example.com',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
            'role' => UserRole::Admin->value,
            'branch_id' => $this->branch->id,
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['username' => 'escalated']);
    }

    #[Test]
    public function manager_cannot_create_a_manager_via_forged_post(): void
    {
        $response = $this->actingAs($this->manager)->withSession($this->passwordConfirmedSession())->post(route('users.store'), [
            'username' => 'peer',
            'email' => 'peer@example.com',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
            'role' => UserRole::Manager->value,
            'branch_id' => $this->branch->id,
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['username' => 'peer']);
    }

    #[Test]
    public function manager_can_create_a_teller_in_own_branch(): void
    {
        $response = $this->actingAs($this->manager)->withSession($this->passwordConfirmedSession())->post(route('users.store'), [
            'username' => 'newteller',
            'email' => 'newteller@example.com',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
            'role' => UserRole::Teller->value,
            'branch_id' => $this->branch->id,
        ]);

        $response->assertRedirect(route('users.index'));

        $teller = User::where('username', 'newteller')->firstOrFail();
        $this->assertSame(UserRole::Teller, $teller->role);
        $this->assertSame($this->branch->id, $teller->branch_id);
    }

    #[Test]
    public function manager_created_user_is_forced_into_managers_branch(): void
    {
        $otherBranch = Branch::factory()->create();

        $this->actingAs($this->manager)->withSession($this->passwordConfirmedSession())->post(route('users.store'), [
            'username' => 'forcedbranch',
            'email' => 'forcedbranch@example.com',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
            'role' => UserRole::Teller->value,
            'branch_id' => $otherBranch->id,
        ]);

        $teller = User::where('username', 'forcedbranch')->firstOrFail();
        $this->assertSame($this->branch->id, $teller->branch_id);
    }

    #[Test]
    public function admin_can_create_an_accountant(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->withSession($this->passwordConfirmedSession())->post(route('users.store'), [
            'username' => 'bean',
            'email' => 'bean@example.com',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
            'role' => UserRole::Accountant->value,
            'branch_id' => $this->branch->id,
        ]);

        $response->assertRedirect(route('users.index'));

        $accountant = User::where('username', 'bean')->firstOrFail();
        $this->assertSame(UserRole::Accountant, $accountant->role);
    }

    #[Test]
    public function admin_cannot_create_a_branch_role_without_a_branch(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->withSession($this->passwordConfirmedSession())->post(route('users.store'), [
            'username' => 'orphanteller',
            'email' => 'orphanteller@example.com',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
            'role' => UserRole::Teller->value,
        ]);

        // A teller without a home branch is a misconfigured account — it
        // would 403 on every branch-scoped page.
        $response->assertSessionHasErrors('branch_id');
        $this->assertDatabaseMissing('users', ['username' => 'orphanteller']);
    }

    #[Test]
    public function admin_can_create_an_office_role_without_a_branch(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->withSession($this->passwordConfirmedSession())->post(route('users.store'), [
            'username' => 'hqaccountant',
            'email' => 'hqaccountant@example.com',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
            'role' => UserRole::Accountant->value,
        ]);

        // Office roles legitimately operate without a branch assignment.
        $response->assertSessionDoesntHaveErrors();
        $this->assertNull(User::where('username', 'hqaccountant')->firstOrFail()->branch_id);
    }

    #[Test]
    public function manager_cannot_promote_a_teller_to_admin(): void
    {
        $teller = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $this->branch->id,
        ]);

        $response = $this->actingAs($this->manager)->withSession($this->passwordConfirmedSession())->put(route('users.update', $teller), [
            'username' => $teller->username,
            'email' => $teller->email,
            'role' => UserRole::Admin->value,
            'branch_id' => $this->branch->id,
            'is_active' => '1',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertSame(UserRole::Teller, $teller->fresh()->role);
    }

    #[Test]
    public function manager_cannot_update_a_same_branch_admin(): void
    {
        $branchAdmin = User::factory()->create([
            'role' => UserRole::Admin,
            'branch_id' => $this->branch->id,
        ]);

        $response = $this->actingAs($this->manager)->withSession($this->passwordConfirmedSession())->put(route('users.update', $branchAdmin), [
            'username' => $branchAdmin->username,
            'email' => $branchAdmin->email,
            'role' => UserRole::Teller->value,
            'branch_id' => $this->branch->id,
            'is_active' => '1',
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function manager_cannot_open_edit_form_for_a_same_branch_admin(): void
    {
        $branchAdmin = User::factory()->create([
            'role' => UserRole::Admin,
            'branch_id' => $this->branch->id,
        ]);

        $this->actingAs($this->manager)
            ->get(route('users.edit', $branchAdmin))
            ->assertForbidden();
    }

    #[Test]
    public function manager_cannot_reset_a_same_branch_admins_password(): void
    {
        $branchAdmin = User::factory()->create([
            'role' => UserRole::Admin,
            'branch_id' => $this->branch->id,
        ]);
        $originalHash = $branchAdmin->password_hash;

        $response = $this->actingAs($this->manager)->withSession($this->passwordConfirmedSession())->post(
            route('users.reset-password', $branchAdmin),
            [
                'password' => 'NewStrongPass123!',
                'password_confirmation' => 'NewStrongPass123!',
            ]
        );

        $response->assertForbidden();
        $this->assertSame($originalHash, $branchAdmin->fresh()->password_hash);
    }

    #[Test]
    public function manager_can_reset_a_same_branch_tellers_password(): void
    {
        $teller = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $this->branch->id,
        ]);
        $originalHash = $teller->password_hash;

        $response = $this->actingAs($this->manager)->withSession($this->passwordConfirmedSession())->post(
            route('users.reset-password', $teller),
            [
                'password' => 'NewStrongPass123!',
                'password_confirmation' => 'NewStrongPass123!',
            ]
        );

        $response->assertRedirect(route('users.index'));
        $this->assertNotSame($originalHash, $teller->fresh()->password_hash);
    }

    #[Test]
    public function user_service_rejects_role_escalation_for_non_admin_actors(): void
    {
        $service = app(UserService::class);

        $this->expectException(UserManagementException::class);

        $service->createUser([
            'username' => 'svc-escalation',
            'email' => 'svc-escalation@example.com',
            'password' => 'StrongPass123!',
            'role' => UserRole::Admin->value,
            'branch_id' => $this->branch->id,
        ], $this->manager->id);
    }

    #[Test]
    public function user_service_rejects_self_role_change(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        User::factory()->create(['role' => UserRole::Admin]); // keep an admin in the pool

        $service = app(UserService::class);

        $this->expectException(UserManagementException::class);
        $this->expectExceptionMessage('You cannot change your own role.');

        $service->updateUser($admin, [
            'username' => $admin->username,
            'email' => $admin->email,
            'role' => UserRole::Teller->value,
            'is_active' => true,
        ], $admin->id);
    }

    #[Test]
    public function user_service_blocks_demoting_the_last_active_admin(): void
    {
        $soleAdmin = User::factory()->create([
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);
        // Inactive admins don't count toward the last-active-admin pool.
        $otherAdmin = User::factory()->create([
            'role' => UserRole::Admin,
            'is_active' => false,
        ]);

        $service = app(UserService::class);

        $this->expectException(UserManagementException::class);
        $this->expectExceptionMessage('Cannot demote or deactivate the last active admin.');

        $service->updateUser($soleAdmin, [
            'username' => $soleAdmin->username,
            'email' => $soleAdmin->email,
            'role' => UserRole::Teller->value,
            'is_active' => true,
        ], $otherAdmin->id);
    }

    #[Test]
    public function user_service_deletes_an_admin_when_another_admin_remains(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Admin]);
        $target = User::factory()->create(['role' => UserRole::Admin]);

        app(UserService::class)->deleteUser($target, $actor->id);

        $this->assertSoftDeleted('users', ['id' => $target->id]);
    }

    #[Test]
    public function user_service_blocks_self_deletion(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $service = app(UserService::class);

        $this->expectException(UserManagementException::class);
        $this->expectExceptionMessage('Cannot delete your own account.');

        $service->deleteUser($admin, $admin->id);
    }
}
