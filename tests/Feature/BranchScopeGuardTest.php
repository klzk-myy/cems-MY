<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * branch.scope middleware coverage on the web ops routes.
 *
 * An account in a branch operating role (teller, manager) is meaningless
 * without a home branch — every page it touches is branch-scoped, so the
 * middleware denies the request outright instead of letting the account
 * drift into a silent consolidated or company-wide view. Cross-branch
 * users (admin, accountant) legitimately have no home branch and pass.
 */
class BranchScopeGuardTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create();
    }

    private function orphan(UserRole $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'branch_id' => null,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function unassigned_branch_roles_are_forbidden_on_ops_pages(): void
    {
        $teller = $this->orphan(UserRole::Teller);
        $manager = $this->orphan(UserRole::Manager);

        foreach (['/rates', '/counters', '/transactions', '/branch-pools', '/stock-cash', '/allocations', '/stock-transfers'] as $url) {
            $this->actingAs($manager)->get($url)->assertForbidden();
            $this->flushSession();
            $this->actingAs($teller)->get($url)->assertForbidden();
            $this->flushSession();
        }
    }

    #[Test]
    public function unassigned_cross_branch_users_pass_the_guard(): void
    {
        $admin = $this->orphan(UserRole::Admin);

        // Admin is cross-branch: the guard exempts it, and each route's own
        // role middleware decides access from there.
        $this->actingAs($admin)->get('/rates')->assertOk();
        $this->actingAs($admin)->get('/branch-pools')->assertOk();
    }

    #[Test]
    public function assigned_branch_users_pass_the_guard(): void
    {
        $manager = User::factory()->create([
            'role' => UserRole::Manager,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $this->actingAs($manager)->get('/rates')->assertOk();
        $this->actingAs($manager)->get('/branch-pools')->assertOk();
    }
}
