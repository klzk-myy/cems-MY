<?php

namespace Tests\Feature;

use App\Enums\TransactionStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\BranchPool;
use App\Models\Compliance\ComplianceFinding;
use App\Models\Customer;
use App\Models\SystemLog;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WebTailEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create();
    }

    protected function createUser(UserRole $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'branch_id' => $role->requiresBranch() ? $this->branch->id : null,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function transaction_print_receipt_renders(): void
    {
        $admin = $this->createUser(UserRole::Admin);
        $transaction = Transaction::factory()->create([
            'branch_id' => $this->branch->id,
            'status' => TransactionStatus::Completed->value,
        ]);

        $this->actingAs($admin)
            ->get("/transactions/{$transaction->id}/print")
            ->assertOk();
    }

    #[Test]
    public function compliance_officer_views_finding_detail(): void
    {
        $officer = $this->createUser(UserRole::ComplianceOfficer);
        $finding = ComplianceFinding::factory()->create();

        $this->actingAs($officer)
            ->get("/compliance/findings/{$finding->id}")
            ->assertOk();
    }

    #[Test]
    public function compliance_officer_dismisses_finding_with_reason(): void
    {
        $officer = $this->createUser(UserRole::ComplianceOfficer);
        $finding = ComplianceFinding::factory()->create();

        $response = $this->actingAs($officer)
            ->post("/compliance/findings/{$finding->id}/dismiss", [
                'reason' => 'False positive — reviewed and cleared.',
            ]);

        $response->assertRedirect();
        $this->assertTrue($finding->fresh()->status->name === 'Dismissed');
    }

    #[Test]
    public function finding_dismiss_requires_reason(): void
    {
        $officer = $this->createUser(UserRole::ComplianceOfficer);
        $finding = ComplianceFinding::factory()->create();

        $this->actingAs($officer)
            ->post("/compliance/findings/{$finding->id}/dismiss", [])
            ->assertSessionHasErrors('reason');
    }

    #[Test]
    public function compliance_officer_triggers_customer_screening(): void
    {
        $officer = $this->createUser(UserRole::ComplianceOfficer);
        $customer = Customer::factory()->create();

        $response = $this->actingAs($officer)
            ->post("/compliance/screening/{$customer->id}");

        $response->assertRedirect();
    }

    #[Test]
    public function admin_views_expense_create_form(): void
    {
        $admin = $this->createUser(UserRole::Admin);

        $this->actingAs($admin)->get('/accounting/expenses/create')->assertOk();
    }

    #[Test]
    public function admin_can_fund_petty_cash_float(): void
    {
        $admin = $this->createUser(UserRole::Admin);

        $response = $this->actingAs($admin)->post('/accounting/expenses/fund', [
            'branch_id' => $this->branch->id,
            'amount_myr' => '500.00',
            'description' => 'Initial petty cash float',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    }

    #[Test]
    public function compliance_officer_exports_str_csv_after_password_confirm(): void
    {
        $officer = $this->createUser(UserRole::ComplianceOfficer);

        $response = $this->actingAs($officer)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get('/str/export');

        $response->assertOk();
    }

    #[Test]
    public function compliance_officer_views_audit_log_entry(): void
    {
        $officer = $this->createUser(UserRole::ComplianceOfficer);
        $log = SystemLog::create([
            'user_id' => $officer->id,
            'action' => 'test_action',
            'description' => 'Test audit entry',
            'severity' => 'INFO',
        ]);

        $this->actingAs($officer)
            ->get("/admin/audit-logs/{$log->id}")
            ->assertOk();
    }

    #[Test]
    public function admin_views_branch_edit_form(): void
    {
        $admin = $this->createUser(UserRole::Admin);

        $this->actingAs($admin)
            ->get("/branches/{$this->branch->id}/edit")
            ->assertOk();
    }

    #[Test]
    public function admin_funds_branch_pool(): void
    {
        $admin = $this->createUser(UserRole::Admin);
        $pool = BranchPool::factory()->create([
            'branch_id' => $this->branch->id,
            'currency_code' => 'USD',
            'available_balance' => '1000.0000',
            'allocated_balance' => '0.0000',
        ]);

        $response = $this->actingAs($admin)
            ->post("/branch-pools/{$pool->id}/fund", [
                'quantity' => '2500',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertEquals('3500.0000', $pool->fresh()->available_balance);
    }
}
