<?php

namespace Tests\Feature\Customers;

use App\Enums\CustomerDocumentStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerFreezeAndKycTest extends TestCase
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
    public function compliance_officer_can_freeze_a_customer(): void
    {
        $officer = $this->createUser(UserRole::ComplianceOfficer);
        $customer = Customer::factory()->create(['is_frozen' => false]);

        $response = $this->actingAs($officer)->post("/customers/{$customer->id}/freeze", [
            'reason' => 'Suspicious activity under review',
        ]);

        $response->assertRedirect();
        $customer->refresh();
        $this->assertTrue($customer->is_frozen);
        $this->assertSame('Suspicious activity under review', $customer->freeze_reason);
        $this->assertNotNull($customer->frozen_at);
    }

    #[Test]
    public function freeze_requires_a_reason(): void
    {
        $officer = $this->createUser(UserRole::ComplianceOfficer);
        $customer = Customer::factory()->create(['is_frozen' => false]);

        $this->actingAs($officer)
            ->post("/customers/{$customer->id}/freeze", [])
            ->assertSessionHasErrors('reason');

        $this->assertFalse($customer->fresh()->is_frozen);
    }

    #[Test]
    public function compliance_officer_can_unfreeze_a_frozen_customer(): void
    {
        $officer = $this->createUser(UserRole::ComplianceOfficer);
        $customer = Customer::factory()->create([
            'is_frozen' => true,
            'freeze_reason' => 'Review',
            'frozen_at' => now(),
        ]);

        $this->actingAs($officer)->post("/customers/{$customer->id}/unfreeze")
            ->assertRedirect();

        $this->assertFalse($customer->fresh()->is_frozen);
    }

    #[Test]
    public function teller_cannot_freeze_or_unfreeze(): void
    {
        $teller = $this->createUser(UserRole::Teller);
        $customer = Customer::factory()->create(['is_frozen' => false]);

        $this->actingAs($teller)->post("/customers/{$customer->id}/freeze", [
            'reason' => 'Attempt by teller',
        ])->assertForbidden();

        $this->assertFalse($customer->fresh()->is_frozen);
    }

    #[Test]
    public function compliance_officer_can_verify_a_pending_kyc_document(): void
    {
        $officer = $this->createUser(UserRole::ComplianceOfficer);
        $document = CustomerDocument::factory()->create([
            'status' => CustomerDocumentStatus::Pending->value,
        ]);

        $this->actingAs($officer)->postJson("/kyc-documents/{$document->id}/verify")
            ->assertOk();

        $document->refresh();
        $this->assertSame(CustomerDocumentStatus::Verified, $document->status);
        $this->assertSame($officer->id, $document->verified_by);
        $this->assertNotNull($document->verified_at);
    }

    #[Test]
    public function compliance_officer_can_reject_a_pending_kyc_document(): void
    {
        $officer = $this->createUser(UserRole::ComplianceOfficer);
        $document = CustomerDocument::factory()->create([
            'status' => CustomerDocumentStatus::Pending->value,
        ]);

        $this->actingAs($officer)->postJson("/kyc-documents/{$document->id}/reject", [
            'reason' => 'Document is expired',
        ])->assertOk();

        $document->refresh();
        $this->assertSame(CustomerDocumentStatus::Rejected, $document->status);
        $this->assertSame('Document is expired', $document->rejection_reason);
    }

    #[Test]
    public function kyc_reject_requires_a_reason(): void
    {
        $officer = $this->createUser(UserRole::ComplianceOfficer);
        $document = CustomerDocument::factory()->create([
            'status' => CustomerDocumentStatus::Pending->value,
        ]);

        $this->actingAs($officer)->postJson("/kyc-documents/{$document->id}/reject", [])
            ->assertUnprocessable();
    }

    #[Test]
    public function verifying_an_already_dispositioned_document_returns_409(): void
    {
        $officer = $this->createUser(UserRole::ComplianceOfficer);
        $document = CustomerDocument::factory()->create([
            'status' => CustomerDocumentStatus::Verified->value,
        ]);

        $this->actingAs($officer)->postJson("/kyc-documents/{$document->id}/verify")
            ->assertStatus(409);
    }
}
