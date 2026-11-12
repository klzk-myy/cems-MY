<?php

namespace Tests\Feature\Api\V1;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\BranchPool;
use App\Models\Counter;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SanctionsScreeningCountersApiTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create();
    }

    protected function createUser(UserRole $role, ?Branch $branch = null): User
    {
        return User::factory()->create([
            'role' => $role,
            'branch_id' => $role->requiresBranch() ? ($branch ?? $this->branch)->id : null,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function sanctions_search_returns_match_envelope(): void
    {
        $officer = $this->createUser(UserRole::ComplianceOfficer);

        $response = $this->actingAs($officer)->postJson('/api/v1/sanctions/search', [
            'name' => 'John Smith',
        ]);

        $response->assertOk();
    }

    #[Test]
    public function sanctions_search_requires_min_length_name(): void
    {
        $officer = $this->createUser(UserRole::ComplianceOfficer);

        $this->actingAs($officer)->postJson('/api/v1/sanctions/search', [
            'name' => 'ab',
        ])->assertUnprocessable();
    }

    #[Test]
    public function admin_can_list_sanction_lists_entries_and_import_logs(): void
    {
        $admin = $this->createUser(UserRole::Admin);

        $this->actingAs($admin)->getJson('/api/v1/sanctions/lists')->assertOk();
        $this->actingAs($admin)->getJson('/api/v1/sanctions/entries')->assertOk();
        $this->actingAs($admin)->getJson('/api/v1/sanctions/import/logs')->assertOk();
    }

    #[Test]
    public function teller_cannot_manage_sanctions(): void
    {
        $teller = $this->createUser(UserRole::Teller);

        $this->actingAs($teller)->getJson('/api/v1/sanctions/lists')->assertForbidden();
        $this->actingAs($teller)->getJson('/api/v1/sanctions/entries')->assertForbidden();
    }

    #[Test]
    public function sanctions_search_requires_compliance_permission(): void
    {
        $teller = $this->createUser(UserRole::Teller);
        $manager = $this->createUser(UserRole::Manager);

        $this->actingAs($teller)->postJson('/api/v1/sanctions/search', [
            'name' => 'John Smith',
        ])->assertForbidden();

        $this->actingAs($manager)->postJson('/api/v1/sanctions/search', [
            'name' => 'John Smith',
        ])->assertForbidden();
    }

    #[Test]
    public function sanctions_search_does_not_persist_for_unauthorised_roles(): void
    {
        $teller = $this->createUser(UserRole::Teller);

        $this->actingAs($teller)->postJson('/api/v1/sanctions/search', [
            'name' => 'John Smith',
        ])->assertForbidden();

        $this->assertDatabaseCount('screening_results', 0);
    }

    #[Test]
    public function screening_batch_screens_multiple_customers(): void
    {
        $officer = $this->createUser(UserRole::ComplianceOfficer);
        $customers = Customer::factory()->count(2)->create();

        $response = $this->actingAs($officer)->postJson('/api/v1/screening/batch', [
            'customer_ids' => $customers->pluck('id')->all(),
        ]);

        $response->assertOk();
    }

    #[Test]
    public function screening_batch_validates_customer_ids(): void
    {
        $officer = $this->createUser(UserRole::ComplianceOfficer);

        $this->actingAs($officer)->postJson('/api/v1/screening/batch', [
            'customer_ids' => [999999],
        ])->assertUnprocessable();
    }

    #[Test]
    public function screening_status_and_history_read_for_customer(): void
    {
        $officer = $this->createUser(UserRole::ComplianceOfficer);
        $customer = Customer::factory()->create();

        $this->actingAs($officer)->getJson("/api/v1/screening/customer/{$customer->id}/status")->assertOk();
        $this->actingAs($officer)->getJson("/api/v1/screening/customer/{$customer->id}/history")->assertOk();
    }

    #[Test]
    public function manager_can_read_own_branch_counters_and_users(): void
    {
        $manager = $this->createUser(UserRole::Manager);
        $other = Branch::factory()->create();

        $this->actingAs($manager)->getJson("/api/v1/branches/{$this->branch->id}/counters")->assertOk();
        $this->actingAs($manager)->getJson("/api/v1/branches/{$this->branch->id}/users")->assertOk();
        $this->actingAs($manager)->getJson("/api/v1/branches/{$other->id}/counters")->assertForbidden();
    }

    #[Test]
    public function teller_can_request_stock_allocation(): void
    {
        $teller = $this->createUser(UserRole::Teller);
        $counter = Counter::factory()->for($this->branch)->create();
        BranchPool::factory()->create([
            'branch_id' => $this->branch->id,
            'currency_code' => 'USD',
            'available_balance' => '100000.0000',
            'allocated_balance' => '0.0000',
        ]);

        $response = $this->actingAs($teller)->postJson('/api/v1/allocations/request', [
            'currency_code' => 'USD',
            'requested_quantity' => '5000',
            'counter_id' => $counter->id,
        ]);

        $response->assertStatus(201);
    }

    #[Test]
    public function teller_reads_own_active_allocation(): void
    {
        $teller = $this->createUser(UserRole::Teller);

        $this->actingAs($teller)->getJson('/api/v1/allocations/my-active?currency_code=USD')->assertOk();
    }

    #[Test]
    public function manager_reads_pending_and_active_allocations(): void
    {
        $manager = $this->createUser(UserRole::Manager);

        $this->actingAs($manager)->getJson('/api/v1/allocations/pending')->assertOk();
        $this->actingAs($manager)->getJson('/api/v1/allocations/active')->assertOk();
    }

    #[Test]
    public function customer_search_returns_results(): void
    {
        $teller = $this->createUser(UserRole::Teller);
        Customer::factory()->create(['full_name' => 'Searchable Person']);

        $response = $this->actingAs($teller)->getJson('/api/v1/customers/search?query=Searchable');

        $response->assertOk();
        $this->assertStringContainsString('Searchable', (string) $response->getContent());
    }

    #[Test]
    public function kyc_document_upload_stores_document(): void
    {
        Storage::fake('local');

        $officer = $this->createUser(UserRole::ComplianceOfficer);
        $customer = Customer::factory()->create();

        $response = $this->actingAs($officer)->post("/api/v1/customers/{$customer->id}/kyc", [
            'document' => UploadedFile::fake()->create('mykad.jpg', 100, 'image/jpeg'),
            'document_type' => 'MyKad',
        ]);

        $response->assertOk();
    }
}
