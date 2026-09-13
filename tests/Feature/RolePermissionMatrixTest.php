<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\TransactionStatus;
use App\Enums\UserRole;
use App\Exceptions\Domain\UserManagementException;
use App\Models\Branch;
use App\Models\RolePermission;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Customer\UserService;
use App\Services\System\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Role-permission matrix enforcement coverage.
 *
 * The role_permissions table is a restrictive overlay on the static
 * UserRole capabilities: revoking a permission narrows a role, but the
 * matrix can never grant a capability the role does not statically hold.
 * Admin is exempt so the operator of the matrix cannot lock itself out.
 */
class RolePermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private PermissionService $permissionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create();
        $this->permissionService = app(PermissionService::class);
    }

    private function makeUser(UserRole $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'branch_id' => $this->branch->id,
        ]);
    }

    #[Test]
    public function unseeded_matrix_preserves_the_static_role_model(): void
    {
        // No role_permissions rows — the built-in defaults apply unchanged.
        $this->assertSame(0, RolePermission::count());

        $this->assertTrue(UserRole::Teller->canCreateTransaction());
        $this->assertFalse(UserRole::Teller->canApproveTransactions());
        $this->assertTrue(UserRole::Manager->canManageUsers());
        $this->assertFalse(UserRole::Manager->canApproveTransactions());
        $this->assertTrue(UserRole::ComplianceOfficer->canApproveTransactions());
        $this->assertTrue(UserRole::Accountant->canAccessAccounting());
        $this->assertTrue(UserRole::Admin->canViewReports());
    }

    #[Test]
    public function revoking_approve_transactions_blocks_compliance_officer_approval(): void
    {
        $officer = $this->makeUser(UserRole::ComplianceOfficer);
        $teller = $this->makeUser(UserRole::Teller);
        $transaction = Transaction::factory()->create([
            'user_id' => $teller->id,
            'branch_id' => $this->branch->id,
        ]);

        $this->assertTrue(Gate::forUser($officer)->allows('approve', $transaction));

        $this->permissionService->updatePermission(
            UserRole::ComplianceOfficer,
            Permission::ApproveTransactions,
            false,
            $officer->id
        );

        $this->assertFalse(UserRole::ComplianceOfficer->canApproveTransactions());
        $this->assertFalse(Gate::forUser($officer->fresh())->allows('approve', $transaction));
    }

    #[Test]
    public function admin_is_exempt_from_matrix_revocation(): void
    {
        $admin = $this->makeUser(UserRole::Admin);

        $this->permissionService->updateRolePermissions(
            UserRole::Admin,
            [Permission::ApproveTransactions->value => false, Permission::ViewReports->value => false],
            $admin->id
        );

        $this->assertTrue(UserRole::Admin->canApproveTransactions());
        $this->assertTrue(UserRole::Admin->canViewReports());
    }

    #[Test]
    public function matrix_cannot_grant_beyond_the_static_ceiling(): void
    {
        $admin = $this->makeUser(UserRole::Admin);

        // Granting tellers view_reports and managers approve_transactions
        // in the matrix must not widen their effective capabilities.
        $this->permissionService->updateRolePermissions(
            UserRole::Teller,
            [Permission::ViewReports->value => true],
            $admin->id
        );
        $this->permissionService->updateRolePermissions(
            UserRole::Manager,
            [Permission::ApproveTransactions->value => true],
            $admin->id
        );

        $this->assertTrue($this->permissionService->can(UserRole::Teller, Permission::ViewReports));
        $this->assertFalse(UserRole::Teller->canViewReports());
        $this->assertFalse(UserRole::Manager->canApproveTransactions());
    }

    #[Test]
    public function revoking_assign_roles_blocks_manager_user_administration(): void
    {
        $manager = $this->makeUser(UserRole::Manager);
        $admin = $this->makeUser(UserRole::Admin);

        $this->assertTrue(Gate::forUser($manager)->allows('create', User::class));

        $this->permissionService->updatePermission(
            UserRole::Manager,
            Permission::AssignRoles,
            false,
            $admin->id
        );

        $this->assertSame([], UserRole::Manager->assignableRoles());
        $this->assertFalse(Gate::forUser($manager->fresh())->allows('create', User::class));

        $this->expectException(UserManagementException::class);
        app(UserService::class)->createUser([
            'username' => 'blocked',
            'email' => 'blocked@example.com',
            'password' => 'StrongPass123!',
            'role' => UserRole::Teller->value,
            'branch_id' => $this->branch->id,
        ], $manager->id);
    }

    #[Test]
    public function revoking_access_compliance_closes_compliance_routes(): void
    {
        $officer = $this->makeUser(UserRole::ComplianceOfficer);
        $admin = $this->makeUser(UserRole::Admin);

        $response = $this->actingAs($officer)->get(route('compliance.alerts.index'));
        $this->assertNotSame(403, $response->getStatusCode());

        $this->permissionService->updatePermission(
            UserRole::ComplianceOfficer,
            Permission::AccessCompliance,
            false,
            $admin->id
        );

        $this->actingAs($officer->fresh())
            ->get(route('compliance.alerts.index'))
            ->assertForbidden();

        // Admin is exempt — the same route still passes for admins.
        $adminResponse = $this->actingAs($admin)->get(route('compliance.alerts.index'));
        $this->assertNotSame(403, $adminResponse->getStatusCode());
    }

    #[Test]
    public function revoking_approve_cancellations_blocks_cancellation_approval(): void
    {
        $manager = $this->makeUser(UserRole::Manager);
        $admin = $this->makeUser(UserRole::Admin);
        $teller = $this->makeUser(UserRole::Teller);
        $transaction = Transaction::factory()->create([
            'user_id' => $teller->id,
            'branch_id' => $this->branch->id,
            'status' => TransactionStatus::PendingCancellation,
        ]);

        $this->assertTrue(Gate::forUser($manager)->allows('approveCancellation', $transaction));

        $this->permissionService->updatePermission(
            UserRole::Manager,
            Permission::ApproveCancellations,
            false,
            $admin->id
        );

        $this->assertFalse(UserRole::Manager->canCancelAnyTransaction());
        $this->assertFalse(Gate::forUser($manager->fresh())->allows('approveCancellation', $transaction));
    }

    #[Test]
    public function revoking_access_accounting_closes_accounting_routes(): void
    {
        $manager = $this->makeUser(UserRole::Manager);
        $admin = $this->makeUser(UserRole::Admin);

        $response = $this->actingAs($manager)->get(route('accounting.index'));
        $this->assertNotSame(403, $response->getStatusCode());

        $this->permissionService->updatePermission(
            UserRole::Manager,
            Permission::AccessAccounting,
            false,
            $admin->id
        );

        // The 'accounting' alias is a module-access check — the revoked
        // manager is denied even though they still hold the manager role.
        $this->actingAs($manager->fresh())
            ->get(route('accounting.index'))
            ->assertForbidden();

        // The accountant keeps access — only the manager's grant was revoked.
        $accountant = $this->makeUser(UserRole::Accountant);
        $accountantResponse = $this->actingAs($accountant)->get(route('accounting.index'));
        $this->assertNotSame(403, $accountantResponse->getStatusCode());
    }

    #[Test]
    public function revoking_manage_users_closes_user_administration_pages(): void
    {
        $manager = $this->makeUser(UserRole::Manager);
        $admin = $this->makeUser(UserRole::Admin);

        $response = $this->actingAs($manager)->get(route('users.index'));
        $this->assertNotSame(403, $response->getStatusCode());

        $this->permissionService->updatePermission(
            UserRole::Manager,
            Permission::ManageUsers,
            false,
            $admin->id
        );

        $this->actingAs($manager->fresh())
            ->get(route('users.index'))
            ->assertForbidden();
    }

    #[Test]
    public function matrix_updates_take_effect_immediately(): void
    {
        $admin = $this->makeUser(UserRole::Admin);

        $this->assertTrue(UserRole::Manager->canViewReports());

        $this->permissionService->updatePermission(
            UserRole::Manager,
            Permission::ViewReports,
            false,
            $admin->id
        );

        $this->assertFalse(UserRole::Manager->canViewReports());

        $this->permissionService->updatePermission(
            UserRole::Manager,
            Permission::ViewReports,
            true,
            $admin->id
        );

        $this->assertTrue(UserRole::Manager->canViewReports());
    }

    #[Test]
    public function can_permission_reports_the_effective_grant(): void
    {
        $manager = $this->makeUser(UserRole::Manager);
        $teller = $this->makeUser(UserRole::Teller);

        $this->assertTrue($manager->canPermission(Permission::ViewReports));
        $this->assertTrue($manager->canPermission('view_reports'));
        $this->assertFalse($teller->canPermission(Permission::ApproveTransactions));
        $this->assertFalse($teller->canPermission('not_a_real_permission'));
    }
}
