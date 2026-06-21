<?php

namespace Tests\Feature\Policies;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Policies\BranchPolicy;
use App\Policies\CounterPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\JournalEntryPolicy;
use App\Policies\TransactionPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    // ─── Transaction Policy ───────────────────────────────────────────

    #[Test]
    public function transaction_policy_allows_any_user_to_view(): void
    {
        $policy = new TransactionPolicy;
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $transaction = Transaction::factory()->create();

        $this->assertTrue($policy->viewAny($teller));
        $this->assertTrue($policy->view($teller, $transaction));
    }

    #[Test]
    public function transaction_policy_allows_teller_to_create(): void
    {
        $policy = new TransactionPolicy;
        $teller = User::factory()->create(['role' => UserRole::Teller]);

        $this->assertTrue($policy->create($teller));
    }

    #[Test]
    public function transaction_policy_allows_manager_to_create(): void
    {
        $policy = new TransactionPolicy;
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $this->assertTrue($policy->create($manager));
    }

    #[Test]
    public function transaction_policy_allows_admin_to_create(): void
    {
        $policy = new TransactionPolicy;
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->assertTrue($policy->create($admin));
    }

    #[Test]
    public function transaction_policy_denies_compliance_officer_to_create(): void
    {
        $policy = new TransactionPolicy;
        $co = User::factory()->create(['role' => UserRole::ComplianceOfficer]);

        $this->assertFalse($policy->create($co));
    }

    #[Test]
    public function transaction_policy_allows_owner_to_update_own(): void
    {
        $policy = new TransactionPolicy;
        $user = User::factory()->create(['role' => UserRole::Teller]);
        $transaction = Transaction::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($policy->update($user, $transaction));
    }

    #[Test]
    public function transaction_policy_denies_non_owner_to_update_other(): void
    {
        $policy = new TransactionPolicy;
        $user = User::factory()->create(['role' => UserRole::Teller]);
        $other = User::factory()->create(['role' => UserRole::Teller]);
        $transaction = Transaction::factory()->create(['user_id' => $other->id]);

        $this->assertFalse($policy->update($user, $transaction));
    }

    #[Test]
    public function transaction_policy_allows_admin_to_update_any(): void
    {
        $policy = new TransactionPolicy;
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $transaction = Transaction::factory()->create();

        $this->assertTrue($policy->update($admin, $transaction));
    }

    #[Test]
    public function transaction_policy_allows_admin_to_delete(): void
    {
        $policy = new TransactionPolicy;
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->assertTrue($policy->delete($admin));
    }

    #[Test]
    public function transaction_policy_denies_teller_to_delete(): void
    {
        $policy = new TransactionPolicy;
        $teller = User::factory()->create(['role' => UserRole::Teller]);

        $this->assertFalse($policy->delete($teller));
    }

    #[Test]
    public function transaction_policy_denies_manager_to_delete(): void
    {
        $policy = new TransactionPolicy;
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $this->assertFalse($policy->delete($manager));
    }

    // ─── Customer Policy ──────────────────────────────────────────────

    #[Test]
    public function customer_policy_allows_any_user_to_view(): void
    {
        $policy = new CustomerPolicy;
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $customer = Customer::factory()->create();

        $this->assertTrue($policy->viewAny($teller));
        $this->assertTrue($policy->view($teller, $customer));
    }

    #[Test]
    public function customer_policy_allows_teller_to_create(): void
    {
        $policy = new CustomerPolicy;
        $teller = User::factory()->create(['role' => UserRole::Teller]);

        $this->assertTrue($policy->create($teller));
    }

    #[Test]
    public function customer_policy_allows_manager_to_create(): void
    {
        $policy = new CustomerPolicy;
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $this->assertTrue($policy->create($manager));
    }

    #[Test]
    public function customer_policy_denies_compliance_officer_to_create(): void
    {
        $policy = new CustomerPolicy;
        $co = User::factory()->create(['role' => UserRole::ComplianceOfficer]);

        $this->assertFalse($policy->create($co));
    }

    #[Test]
    public function customer_policy_allows_manager_to_update(): void
    {
        $policy = new CustomerPolicy;
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $customer = Customer::factory()->create();

        $this->assertTrue($policy->update($manager, $customer));
    }

    #[Test]
    public function customer_policy_allows_admin_to_update(): void
    {
        $policy = new CustomerPolicy;
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $customer = Customer::factory()->create();

        $this->assertTrue($policy->update($admin, $customer));
    }

    #[Test]
    public function customer_policy_denies_teller_to_update(): void
    {
        $policy = new CustomerPolicy;
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $customer = Customer::factory()->create();

        $this->assertFalse($policy->update($teller, $customer));
    }

    #[Test]
    public function customer_policy_allows_admin_to_delete(): void
    {
        $policy = new CustomerPolicy;
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->assertTrue($policy->delete($admin));
    }

    #[Test]
    public function customer_policy_denies_manager_to_delete(): void
    {
        $policy = new CustomerPolicy;
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $this->assertFalse($policy->delete($manager));
    }

    // ─── Branch Policy ────────────────────────────────────────────────

    #[Test]
    public function branch_policy_allows_any_user_to_view(): void
    {
        $policy = new BranchPolicy;
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $branch = Branch::factory()->create();

        $this->assertTrue($policy->viewAny($teller));
        $this->assertTrue($policy->view($teller, $branch));
    }

    #[Test]
    public function branch_policy_allows_admin_to_create(): void
    {
        $policy = new BranchPolicy;
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->assertTrue($policy->create($admin));
    }

    #[Test]
    public function branch_policy_denies_manager_to_create(): void
    {
        $policy = new BranchPolicy;
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $this->assertFalse($policy->create($manager));
    }

    #[Test]
    public function branch_policy_denies_teller_to_create(): void
    {
        $policy = new BranchPolicy;
        $teller = User::factory()->create(['role' => UserRole::Teller]);

        $this->assertFalse($policy->create($teller));
    }

    #[Test]
    public function branch_policy_allows_admin_to_update(): void
    {
        $policy = new BranchPolicy;
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $branch = Branch::factory()->create();

        $this->assertTrue($policy->update($admin, $branch));
    }

    #[Test]
    public function branch_policy_denies_manager_to_update(): void
    {
        $policy = new BranchPolicy;
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $branch = Branch::factory()->create();

        $this->assertFalse($policy->update($manager, $branch));
    }

    #[Test]
    public function branch_policy_allows_admin_to_delete(): void
    {
        $policy = new BranchPolicy;
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->assertTrue($policy->delete($admin));
    }

    #[Test]
    public function branch_policy_denies_teller_to_delete(): void
    {
        $policy = new BranchPolicy;
        $teller = User::factory()->create(['role' => UserRole::Teller]);

        $this->assertFalse($policy->delete($teller));
    }

    // ─── Counter Policy ───────────────────────────────────────────────

    #[Test]
    public function counter_policy_allows_any_user_to_view(): void
    {
        $policy = new CounterPolicy;
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $counter = Counter::factory()->create();

        $this->assertTrue($policy->viewAny($teller));
        $this->assertTrue($policy->view($teller, $counter));
    }

    #[Test]
    public function counter_policy_allows_manager_to_create(): void
    {
        $policy = new CounterPolicy;
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $this->assertTrue($policy->create($manager));
    }

    #[Test]
    public function counter_policy_allows_admin_to_create(): void
    {
        $policy = new CounterPolicy;
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->assertTrue($policy->create($admin));
    }

    #[Test]
    public function counter_policy_denies_teller_to_create(): void
    {
        $policy = new CounterPolicy;
        $teller = User::factory()->create(['role' => UserRole::Teller]);

        $this->assertFalse($policy->create($teller));
    }

    #[Test]
    public function counter_policy_allows_manager_to_update(): void
    {
        $policy = new CounterPolicy;
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $counter = Counter::factory()->create();

        $this->assertTrue($policy->update($manager, $counter));
    }

    #[Test]
    public function counter_policy_denies_teller_to_update(): void
    {
        $policy = new CounterPolicy;
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $counter = Counter::factory()->create();

        $this->assertFalse($policy->update($teller, $counter));
    }

    #[Test]
    public function counter_policy_allows_admin_to_delete(): void
    {
        $policy = new CounterPolicy;
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->assertTrue($policy->delete($admin));
    }

    #[Test]
    public function counter_policy_denies_manager_to_delete(): void
    {
        $policy = new CounterPolicy;
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $this->assertFalse($policy->delete($manager));
    }

    // ─── User Policy ──────────────────────────────────────────────────

    #[Test]
    public function user_policy_allows_manager_to_view_any(): void
    {
        $policy = new UserPolicy;
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $this->assertTrue($policy->viewAny($manager));
    }

    #[Test]
    public function user_policy_denies_teller_to_view_any(): void
    {
        $policy = new UserPolicy;
        $teller = User::factory()->create(['role' => UserRole::Teller]);

        $this->assertFalse($policy->viewAny($teller));
    }

    #[Test]
    public function user_policy_allows_user_to_view_self(): void
    {
        $policy = new UserPolicy;
        $user = User::factory()->create(['role' => UserRole::Teller]);

        $this->assertTrue($policy->view($user, $user));
    }

    #[Test]
    public function user_policy_denies_teller_to_view_other(): void
    {
        $policy = new UserPolicy;
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $other = User::factory()->create(['role' => UserRole::Teller]);

        $this->assertFalse($policy->view($teller, $other));
    }

    #[Test]
    public function user_policy_allows_manager_to_view_any_user(): void
    {
        $policy = new UserPolicy;
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $other = User::factory()->create(['role' => UserRole::Teller]);

        $this->assertTrue($policy->view($manager, $other));
    }

    #[Test]
    public function user_policy_allows_admin_to_create(): void
    {
        $policy = new UserPolicy;
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->assertTrue($policy->create($admin));
    }

    #[Test]
    public function user_policy_denies_manager_to_create(): void
    {
        $policy = new UserPolicy;
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $this->assertFalse($policy->create($manager));
    }

    #[Test]
    public function user_policy_allows_user_to_update_self(): void
    {
        $policy = new UserPolicy;
        $user = User::factory()->create(['role' => UserRole::Teller]);

        $this->assertTrue($policy->update($user, $user));
    }

    #[Test]
    public function user_policy_denies_teller_to_update_other(): void
    {
        $policy = new UserPolicy;
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $other = User::factory()->create(['role' => UserRole::Teller]);

        $this->assertFalse($policy->update($teller, $other));
    }

    #[Test]
    public function user_policy_allows_admin_to_update_any(): void
    {
        $policy = new UserPolicy;
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $other = User::factory()->create(['role' => UserRole::Teller]);

        $this->assertTrue($policy->update($admin, $other));
    }

    #[Test]
    public function user_policy_allows_admin_to_delete(): void
    {
        $policy = new UserPolicy;
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->assertTrue($policy->delete($admin));
    }

    #[Test]
    public function user_policy_denies_manager_to_delete(): void
    {
        $policy = new UserPolicy;
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $this->assertFalse($policy->delete($manager));
    }

    // ─── JournalEntry Policy ──────────────────────────────────────────

    #[Test]
    public function journal_entry_policy_allows_manager_to_view(): void
    {
        $policy = new JournalEntryPolicy;
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $entry = JournalEntry::factory()->create();

        $this->assertTrue($policy->viewAny($manager));
        $this->assertTrue($policy->view($manager, $entry));
    }

    #[Test]
    public function journal_entry_policy_denies_teller_to_view(): void
    {
        $policy = new JournalEntryPolicy;
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $entry = JournalEntry::factory()->create();

        $this->assertFalse($policy->viewAny($teller));
        $this->assertFalse($policy->view($teller, $entry));
    }

    #[Test]
    public function journal_entry_policy_allows_manager_to_create(): void
    {
        $policy = new JournalEntryPolicy;
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $this->assertTrue($policy->create($manager));
    }

    #[Test]
    public function journal_entry_policy_denies_teller_to_create(): void
    {
        $policy = new JournalEntryPolicy;
        $teller = User::factory()->create(['role' => UserRole::Teller]);

        $this->assertFalse($policy->create($teller));
    }

    #[Test]
    public function journal_entry_policy_allows_manager_to_update(): void
    {
        $policy = new JournalEntryPolicy;
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $entry = JournalEntry::factory()->create();

        $this->assertTrue($policy->update($manager, $entry));
    }

    #[Test]
    public function journal_entry_policy_denies_teller_to_update(): void
    {
        $policy = new JournalEntryPolicy;
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $entry = JournalEntry::factory()->create();

        $this->assertFalse($policy->update($teller, $entry));
    }

    #[Test]
    public function journal_entry_policy_allows_admin_to_delete(): void
    {
        $policy = new JournalEntryPolicy;
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->assertTrue($policy->delete($admin));
    }

    #[Test]
    public function journal_entry_policy_denies_manager_to_delete(): void
    {
        $policy = new JournalEntryPolicy;
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $this->assertFalse($policy->delete($manager));
    }
}
