<?php

namespace Tests\Unit\Models;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_branch_relationship(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);

        $this->assertTrue($user->branch->is($branch));
    }

    public function test_user_has_many_transactions(): void
    {
        $user = User::factory()->create();
        Transaction::factory()->count(3)->create(['user_id' => $user->id]);

        $this->assertCount(3, $user->transactions);
    }

    public function test_password_is_hashed_and_read_via_password_attribute(): void
    {
        $user = User::factory()->make(['password' => 'secret']);

        $this->assertNotEquals('secret', $user->password_hash);
        $this->assertSame($user->password_hash, $user->password);
    }

    public function test_role_checks(): void
    {
        $this->assertTrue(User::factory()->make(['role' => UserRole::Admin])->isAdmin());
        $this->assertTrue(User::factory()->make(['role' => UserRole::Manager])->isManager());
        $this->assertTrue(User::factory()->make(['role' => UserRole::ComplianceOfficer])->isComplianceOfficer());
        $this->assertTrue(User::factory()->make(['role' => UserRole::Teller])->isTeller());
    }
}
