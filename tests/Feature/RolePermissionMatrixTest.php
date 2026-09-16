<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\TransactionStatus;
use App\Enums\UserRole;
use App\Exceptions\Domain\UserManagementException;
use App\Models\AccountingPeriod;
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
 * The role_permissions table is the authoritative grant set for dynamic
 * permissions: seeded from the built-in UserRole defaults, an admin may
 * widen it to grant any permission to any role or narrow it to revoke
 * built-in capabilities. Admin is exempt so the operator of the matrix
 * cannot lock itself out.
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

    /**
     * With no role_permissions rows the built-in defaults apply unchanged.
     */
    private function assertBuiltinDefaultsApply(): void
    {
        $this->assertSame(0, RolePermission::count());
    }

    #[Test]
    public function unseeded_matrix_preserves_the_static_role_model(): void
    {
        $this->assertBuiltinDefaultsApply();

        $this->assertTrue(UserRole::Teller->canCreateTransaction());
        $this->assertFalse(UserRole::Teller->canApproveTransactions());
        $this->assertTrue(UserRole::Manager->canManageUsers());
        $this->assertFalse(UserRole::Manager->canApproveTransactions());
        $this->assertTrue(UserRole::ComplianceOfficer->canApproveTransactions());
        $this->assertTrue(UserRole::Accountant->canAccessAccounting());
        $this->assertTrue(UserRole::Admin->canViewReports());
    }

    #[Test]
    public function accountant_is_granted_cross_branch_access_by_default(): void
    {
        $this->assertBuiltinDefaultsApply();

        // Accountants have company-wide cross-branch access by default
        // (CLAUDE.md: "Accountants have company-wide accounting and reports
        // with cross-branch access"). The default matrix must seed
        // ManageAllBranches so canManageAllBranches() returns true.
        $this->assertTrue(UserRole::Accountant->canManageAllBranches());
        $this->assertTrue($this->permissionService->can(UserRole::Accountant, Permission::ManageAllBranches));

        // Revoking the matrix grant narrows the accountant to branch-scoped.
        $admin = $this->makeUser(UserRole::Admin);
        $this->permissionService->updatePermission(
            UserRole::Accountant,
            Permission::ManageAllBranches,
            false,
            $admin->id
        );

        $this->assertFalse(UserRole::Accountant->canManageAllBranches());
    }

    #[Test]
    public function accountant_is_granted_the_accounting_permissions_by_default(): void
    {
        $this->assertBuiltinDefaultsApply();

        // Enumerated rather than groupedByCategory()['Accounting'] so a new
        // permission added to the category is a deliberate grant decision,
        // not an automatic one.
        foreach ([
            Permission::AccessAccounting,
            Permission::ManageAccounting,
            Permission::PostExpenses,
            Permission::PostJournalEntries,
        ] as $permission) {
            $this->assertTrue(
                UserRole::Accountant->canPerform($permission),
                "Accountant should be granted {$permission->value} by default"
            );
            $this->assertTrue($this->permissionService->can(UserRole::Accountant, $permission));
        }
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
    public function admin_page_renders_a_checkbox_for_every_role_permission_pair(): void
    {
        $admin = $this->makeUser(UserRole::Admin);

        $response = $this->actingAs($admin)->get(route('admin.role-permissions.index'));

        $response->assertOk();
        // Nested array syntax — the browser must submit the same
        // permissions[role][permission] shape validateUpdateRequest() reads.
        $response->assertSee('name="permissions[teller][approve_transactions]"', false);
        $response->assertSee('name="permissions[manager][reverse_transactions]"', false);
        $response->assertSee('name="permissions[accountant][manage_users]"', false);
    }

    #[Test]
    public function update_endpoint_grants_any_permission_to_any_role(): void
    {
        $admin = $this->makeUser(UserRole::Admin);

        $response = $this->actingAs($admin)
            ->withSession($this->passwordConfirmedSession())
            ->post(route('admin.role-permissions.update'), [
                'permissions' => [
                    UserRole::Teller->value => [
                        Permission::CreateTransactions->value => '1',
                        Permission::ApproveTransactions->value => '1',
                        Permission::ViewReports->value => '1',
                    ],
                ],
            ]);

        $response->assertRedirect(route('admin.role-permissions.index'));

        $this->assertTrue(UserRole::Teller->canApproveTransactions());
        $this->assertTrue(UserRole::Teller->canViewReports());
        $this->assertTrue(UserRole::Teller->canCreateTransaction());
    }

    #[Test]
    public function unchecked_browser_checkboxes_revoke_the_grant(): void
    {
        $admin = $this->makeUser(UserRole::Admin);

        // The browser only submits checked boxes: an absent key means the
        // admin unticked the permission, which must revoke the grant.
        $response = $this->actingAs($admin)
            ->withSession($this->passwordConfirmedSession())
            ->post(route('admin.role-permissions.update'), [
                'permissions' => [
                    UserRole::Teller->value => [
                        Permission::CreateTransactions->value => '1',
                        Permission::RequestCancellation->value => '1',
                        Permission::OperateCounters->value => '1',
                        Permission::ValidateRates->value => '1',
                        // request_stock deliberately absent — unticked.
                    ],
                ],
            ]);

        $response->assertRedirect(route('admin.role-permissions.index'));

        $this->assertFalse(UserRole::Teller->canPerform(Permission::RequestStock));
        $this->assertTrue(UserRole::Teller->canCreateTransaction());
    }

    #[Test]
    public function default_action_restores_the_built_in_matrix(): void
    {
        $admin = $this->makeUser(UserRole::Admin);

        // Widen the teller beyond its built-in defaults, then restore.
        $this->permissionService->updateRolePermissions(
            UserRole::Teller,
            [Permission::ViewReports->value => true, Permission::ApproveTransactions->value => true],
            $admin->id
        );
        $this->assertTrue(UserRole::Teller->canViewReports());

        $response = $this->actingAs($admin)
            ->withSession($this->passwordConfirmedSession())
            ->post(route('admin.role-permissions.update'), ['action' => 'default']);

        $response->assertRedirect(route('admin.role-permissions.index'));
        $response->assertSessionHas('success', 'Role permissions restored to defaults.');

        $this->assertFalse(UserRole::Teller->canViewReports());
        $this->assertFalse(UserRole::Teller->canApproveTransactions());
        $this->assertTrue(UserRole::Teller->canCreateTransaction());
    }

    #[Test]
    public function matches_role_alias_mirrors_middleware_semantics(): void
    {
        // Identity aliases, including admin's manager/accountant inheritance.
        $this->assertTrue(UserRole::Manager->matchesRoleAlias('manager'));
        $this->assertTrue(UserRole::Admin->matchesRoleAlias('manager'));
        $this->assertFalse(UserRole::Teller->matchesRoleAlias('manager'));
        $this->assertTrue(UserRole::Admin->matchesRoleAlias('admin'));
        $this->assertFalse(UserRole::Manager->matchesRoleAlias('admin'));

        // Module aliases are effective-permission checks.
        $this->assertTrue(UserRole::ComplianceOfficer->matchesRoleAlias('compliance'));
        $this->assertTrue(UserRole::Admin->matchesRoleAlias('compliance'));
        $this->assertFalse(UserRole::Teller->matchesRoleAlias('compliance'));
        $this->assertTrue(UserRole::Manager->matchesRoleAlias('users'));
        $this->assertTrue(UserRole::Accountant->matchesRoleAlias('accounting'));
        $this->assertFalse(UserRole::Teller->matchesRoleAlias('accounting'));

        $this->expectException(\InvalidArgumentException::class);
        UserRole::Admin->matchesRoleAlias('not_a_real_alias');
    }

    #[Test]
    public function granting_a_module_permission_satisfies_its_role_alias(): void
    {
        $admin = $this->makeUser(UserRole::Admin);

        $this->assertFalse(UserRole::Teller->matchesRoleAlias('compliance'));

        $this->permissionService->updateRolePermissions(
            UserRole::Teller,
            [Permission::AccessCompliance->value => true],
            $admin->id
        );

        $this->assertTrue(UserRole::Teller->matchesRoleAlias('compliance'));
    }

    #[Test]
    public function sidebar_shows_links_matching_effective_permissions(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        $teller = $this->makeUser(UserRole::Teller);

        // Admin inherits manager/accountant identity and holds every module
        // permission — all module links must render, not just literal matches.
        $adminNav = $this->actingAs($admin)->get(route('dashboard'));
        $adminNav->assertSee(route('compliance'), false);
        $adminNav->assertSee(route('accounting.index'), false);
        $adminNav->assertSee(route('users.index'), false);

        // The teller has no module grants — those links stay hidden.
        $tellerNav = $this->actingAs($teller)->get(route('dashboard'));
        $tellerNav->assertDontSee(route('compliance'), false);
        $tellerNav->assertDontSee(route('accounting.index'), false);
        $tellerNav->assertDontSee(route('users.index'), false);

        // Granting access_compliance to the teller surfaces the link.
        $this->permissionService->updateRolePermissions(
            UserRole::Teller,
            [Permission::AccessCompliance->value => true],
            $admin->id
        );

        $this->actingAs($teller->fresh())
            ->get(route('dashboard'))
            ->assertSee(route('compliance'), false);
    }

    #[Test]
    public function matrix_grants_beyond_the_built_in_defaults_take_effect(): void
    {
        $admin = $this->makeUser(UserRole::Admin);

        // Granting tellers view_reports and managers approve_transactions
        // in the matrix widens their effective capabilities — the matrix
        // is the authoritative grant set, not a narrowing overlay.
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
        $this->assertTrue(UserRole::Teller->canViewReports());
        $this->assertTrue(UserRole::Manager->canApproveTransactions());
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
    public function revoking_manage_accounting_blocks_period_close_and_revaluation(): void
    {
        $manager = $this->makeUser(UserRole::Manager);
        $admin = $this->makeUser(UserRole::Admin);
        $period = AccountingPeriod::factory()->create();

        // The default grant passes the authorize gate — a redirect (even a
        // business-logic error flash) means the request was authorized.
        $response = $this->actingAs($manager)->post(route('accounting.period.close', $period), [
            'period_id' => $period->id,
            'closure_date' => now()->toDateString(),
            'reason' => 'Monthly period close',
        ]);
        $this->assertNotSame(403, $response->getStatusCode());

        $response = $this->actingAs($manager)->post(route('accounting.revaluation.run'));
        $this->assertNotSame(403, $response->getStatusCode());

        $this->permissionService->updatePermission(
            UserRole::Manager,
            Permission::ManageAccounting,
            false,
            $admin->id
        );

        $this->actingAs($manager->fresh())
            ->post(route('accounting.period.close', $period), [
                'period_id' => $period->id,
                'closure_date' => now()->toDateString(),
                'reason' => 'Monthly period close',
            ])
            ->assertForbidden();

        $this->actingAs($manager->fresh())
            ->post(route('accounting.revaluation.run'))
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
    public function permission_keys_resolve_as_role_middleware_arguments(): void
    {
        // Every Permission key is a valid role: argument, resolved through
        // the matrix — the dominant form now used across the route files.
        $this->assertTrue(UserRole::Manager->matchesRoleAlias('view_reports'));
        $this->assertTrue(UserRole::Manager->matchesRoleAlias('manage_counters'));
        $this->assertFalse(UserRole::Teller->matchesRoleAlias('manage_counters'));
        $this->assertTrue(UserRole::Teller->matchesRoleAlias('create_transactions'));
        $this->assertFalse(UserRole::Accountant->matchesRoleAlias('create_transactions'));
        $this->assertTrue(UserRole::ComplianceOfficer->matchesRoleAlias('access_compliance'));

        // Admin is exempt from the matrix — every permission key passes.
        $this->assertTrue(UserRole::Admin->matchesRoleAlias('manage_dlq'));
        $this->assertTrue(UserRole::Admin->matchesRoleAlias('manage_currencies'));
    }

    #[Test]
    public function granting_view_reports_unlocks_the_reports_module(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        $teller = $this->makeUser(UserRole::Teller);

        $this->actingAs($teller)->get(route('reports.index'))->assertForbidden();

        $this->permissionService->updatePermission(
            UserRole::Teller,
            Permission::ViewReports,
            true,
            $admin->id
        );

        $response = $this->actingAs($teller->fresh())->get(route('reports.index'));
        $this->assertNotSame(403, $response->getStatusCode());
    }

    #[Test]
    public function granting_manage_counters_unlocks_counter_administration(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        $teller = $this->makeUser(UserRole::Teller);

        $this->actingAs($teller)->get(route('counters.create'))->assertForbidden();

        $this->permissionService->updatePermission(
            UserRole::Teller,
            Permission::ManageCounters,
            true,
            $admin->id
        );

        $response = $this->actingAs($teller->fresh())->get(route('counters.create'));
        $this->assertNotSame(403, $response->getStatusCode());
    }

    #[Test]
    public function revoking_create_transactions_closes_the_teller_wizard(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        $teller = $this->makeUser(UserRole::Teller);

        $response = $this->actingAs($teller)->get(route('transactions.wizard'));
        $this->assertNotSame(403, $response->getStatusCode());

        $this->permissionService->updatePermission(
            UserRole::Teller,
            Permission::CreateTransactions,
            false,
            $admin->id
        );

        $this->actingAs($teller->fresh())
            ->get(route('transactions.wizard'))
            ->assertForbidden();
    }

    #[Test]
    public function admin_reaches_every_module_route(): void
    {
        $admin = $this->makeUser(UserRole::Admin);

        foreach (['reports.index', 'rates.index', 'accounting.index', 'users.index', 'counters.create', 'system.currencies.index', 'admin.role-permissions.index'] as $routeName) {
            $response = $this->actingAs($admin)->get(route($routeName));
            $this->assertNotSame(403, $response->getStatusCode(), "Admin was forbidden from {$routeName}");
        }
    }

    #[Test]
    public function sidebar_tracks_matrix_grants_on_permission_gated_routes(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        $teller = $this->makeUser(UserRole::Teller);

        // Reports is gated by role:view_reports — a teller has no grant.
        $this->actingAs($teller)->get(route('dashboard'))
            ->assertDontSee(route('reports.index'), false);

        $this->permissionService->updatePermission(
            UserRole::Teller,
            Permission::ViewReports,
            true,
            $admin->id
        );

        $this->actingAs($teller->fresh())->get(route('dashboard'))
            ->assertSee(route('reports.index'), false);
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
