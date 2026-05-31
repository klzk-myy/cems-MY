<?php

namespace Tests\Unit;

use App\Enums\ApprovalLevel;
use App\Enums\PepType;
use App\Enums\RiskRating;
use App\Models\Customer;
use App\Models\PepApprovalRequest;
use App\Models\User;
use App\Services\PepApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for PepApprovalService
 *
 * Tests head office Senior Management approval for PEP customers per pd-00.md 14C.13.1(d):
 * "obtaining approval from the Senior Management of the reporting institution before establishing
 * (or continuing, for existing customer) such business relationship with the customer.
 * In the case of PEPs, Senior Management refers to Senior Management at the head office."
 */
class PepApprovalServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PepApprovalService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PepApprovalService;
    }

    /**
     * Test that Foreign PEPs always require head office approval.
     */
    public function test_foreign_pep_requires_head_office_approval(): void
    {
        $customer = Customer::factory()->create([
            'pep_status' => true,
            'pep_type' => PepType::Foreign->value,
            'risk_rating' => RiskRating::Low,
        ]);

        $this->assertTrue($this->service->requiresHeadOfficeApproval($customer));
    }

    /**
     * Test that non-PEP customers do not require head office approval.
     */
    public function test_non_pep_does_not_require_head_office_approval(): void
    {
        $customer = Customer::factory()->create([
            'pep_status' => false,
        ]);

        $this->assertFalse($this->service->requiresHeadOfficeApproval($customer));
    }

    /**
     * Test that Domestic PEPs with Low risk do NOT require head office approval.
     */
    public function test_domestic_pep_low_risk_does_not_require_head_office_approval(): void
    {
        $customer = Customer::factory()->create([
            'pep_status' => true,
            'pep_type' => PepType::Domestic->value,
            'risk_rating' => RiskRating::Low,
        ]);

        $this->assertFalse($this->service->requiresHeadOfficeApproval($customer));
    }

    /**
     * Test that Domestic PEPs with Medium risk DO require head office approval.
     */
    public function test_domestic_pep_medium_risk_requires_head_office_approval(): void
    {
        $customer = Customer::factory()->create([
            'pep_status' => true,
            'pep_type' => PepType::Domestic->value,
            'risk_rating' => RiskRating::Medium,
        ]);

        $this->assertTrue($this->service->requiresHeadOfficeApproval($customer));
    }

    /**
     * Test that Domestic PEPs with High risk DO require head office approval.
     */
    public function test_domestic_pep_high_risk_requires_head_office_approval(): void
    {
        $customer = Customer::factory()->create([
            'pep_status' => true,
            'pep_type' => PepType::Domestic->value,
            'risk_rating' => RiskRating::High,
        ]);

        $this->assertTrue($this->service->requiresHeadOfficeApproval($customer));
    }

    /**
     * Test that International Organisation PEPs with High risk require head office approval.
     */
    public function test_international_org_pep_high_risk_requires_head_office_approval(): void
    {
        $customer = Customer::factory()->create([
            'pep_status' => true,
            'pep_type' => PepType::InternationalOrg->value,
            'risk_rating' => RiskRating::High,
        ]);

        $this->assertTrue($this->service->requiresHeadOfficeApproval($customer));
    }

    /**
     * Test that Family Member PEPs with Low risk do NOT require head office approval.
     */
    public function test_family_member_pep_low_risk_does_not_require_head_office_approval(): void
    {
        $customer = Customer::factory()->create([
            'pep_status' => true,
            'pep_type' => PepType::FamilyMember->value,
            'risk_rating' => RiskRating::Low,
        ]);

        $this->assertFalse($this->service->requiresHeadOfficeApproval($customer));
    }

    /**
     * Test requesting approval creates a pending PepApprovalRequest.
     */
    public function test_request_approval_creates_pending_request(): void
    {
        $customer = Customer::factory()->create([
            'pep_status' => true,
            'pep_type' => PepType::Foreign->value,
            'risk_rating' => RiskRating::Low,
        ]);

        $request = $this->service->requestApproval($customer, 'new_relationship');

        $this->assertInstanceOf(PepApprovalRequest::class, $request);
        $this->assertEquals($customer->id, $request->customer_id);
        $this->assertEquals('new_relationship', $request->transaction_type);
        $this->assertTrue($request->isPending());
        $this->assertEquals(ApprovalLevel::HeadOfficeSeniorManagement, $request->approval_level);
    }

    /**
     * Test approving a request updates status correctly.
     */
    public function test_approve_updates_status_to_approved(): void
    {
        $customer = Customer::factory()->create([
            'pep_status' => true,
            'pep_type' => PepType::Foreign->value,
            'risk_rating' => RiskRating::Low,
        ]);

        $request = $this->service->requestApproval($customer, 'new_relationship');
        $approver = User::factory()->create();

        $this->service->approve($request, $approver);

        $request->refresh();
        $this->assertTrue($request->isApproved());
        $this->assertEquals($approver->id, $request->approved_by);
        $this->assertNotNull($request->approved_at);
    }

    /**
     * Test rejecting a request updates status and stores reason.
     */
    public function test_reject_updates_status_and_stores_reason(): void
    {
        $customer = Customer::factory()->create([
            'pep_status' => true,
            'pep_type' => PepType::Foreign->value,
            'risk_rating' => RiskRating::Low,
        ]);

        $request = $this->service->requestApproval($customer, 'new_relationship');
        $rejector = User::factory()->create();
        $reason = 'Insufficient documentation provided';

        $this->service->reject($request, $rejector, $reason);

        $request->refresh();
        $this->assertTrue($request->isRejected());
        $this->assertEquals($rejector->id, $request->rejected_by);
        $this->assertNotNull($request->rejected_at);
        $this->assertEquals($reason, $request->rejection_reason);
    }

    /**
     * Test hasPendingApproval returns true when pending request exists.
     */
    public function test_has_pending_approval_returns_true_when_pending(): void
    {
        $customer = Customer::factory()->create([
            'pep_status' => true,
            'pep_type' => PepType::Foreign->value,
            'risk_rating' => RiskRating::Low,
        ]);

        $this->service->requestApproval($customer, 'new_relationship');

        $this->assertTrue($this->service->hasPendingApproval($customer));
    }

    /**
     * Test hasApprovedApproval returns true when approved request exists.
     */
    public function test_has_approved_approval_returns_true_when_approved(): void
    {
        $customer = Customer::factory()->create([
            'pep_status' => true,
            'pep_type' => PepType::Foreign->value,
            'risk_rating' => RiskRating::Low,
        ]);

        $request = $this->service->requestApproval($customer, 'new_relationship');
        $approver = User::factory()->create();
        $this->service->approve($request, $approver);

        $this->assertTrue($this->service->hasApprovedApproval($customer));
    }

    /**
     * Test getPendingApproval returns the pending request.
     */
    public function test_get_pending_approval_returns_pending_request(): void
    {
        $customer = Customer::factory()->create([
            'pep_status' => true,
            'pep_type' => PepType::Foreign->value,
            'risk_rating' => RiskRating::Low,
        ]);

        $request = $this->service->requestApproval($customer, 'new_relationship');

        $pending = $this->service->getPendingApproval($customer);

        $this->assertNotNull($pending);
        $this->assertEquals($request->id, $pending->id);
    }
}
