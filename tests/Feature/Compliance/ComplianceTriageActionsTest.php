<?php

namespace Tests\Feature\Compliance;

use App\Enums\ComplianceCaseStatus;
use App\Enums\ComplianceCaseType;
use App\Enums\EddDocumentStatus;
use App\Enums\FindingStatus;
use App\Enums\FlagStatus;
use App\Enums\UserRole;
use App\Models\Compliance\ComplianceCase;
use App\Models\Compliance\ComplianceFinding;
use App\Models\Compliance\EddDocumentRequest;
use App\Models\Compliance\EnhancedDiligenceRecord;
use App\Models\Compliance\FlaggedTransaction;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Compliance triage HTTP actions: flag assignment/resolution, finding ->
 * case promotion, case escalation, EDD document upload (customer-facing
 * signed link), risk rescreening, and customer closure.
 */
class ComplianceTriageActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->officer = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
    }

    #[Test]
    public function assign_flag_moves_it_to_under_review_and_claims_it(): void
    {
        $flag = FlaggedTransaction::factory()->create(['status' => FlagStatus::Open->value]);

        $this->actingAs($this->officer)
            ->patch(route('compliance.flags.assign', $flag))
            ->assertRedirect()
            ->assertSessionHas('success', 'Flag assigned to you for review.');

        $flag->refresh();
        $this->assertSame(FlagStatus::UnderReview, $flag->status);
        $this->assertSame($this->officer->id, $flag->assigned_to);
    }

    #[Test]
    public function resolve_flag_marks_it_resolved_with_the_reviewer(): void
    {
        $flag = FlaggedTransaction::factory()->create([
            'status' => FlagStatus::UnderReview->value,
            'assigned_to' => $this->officer->id,
        ]);

        $this->actingAs($this->officer)
            ->patch(route('compliance.flags.resolve', $flag))
            ->assertRedirect()
            ->assertSessionHas('success', 'Flag marked as resolved.');

        $flag->refresh();
        $this->assertSame(FlagStatus::Resolved, $flag->status);
        $this->assertSame($this->officer->id, $flag->reviewed_by);
        $this->assertNotNull($flag->resolved_at);
    }

    #[Test]
    public function a_case_can_be_created_from_a_new_finding(): void
    {
        $finding = ComplianceFinding::factory()->create(['status' => FindingStatus::New]);

        $response = $this->actingAs($this->officer)
            ->post(route('compliance.findings.create-case', $finding->id), [
                'case_type' => ComplianceCaseType::Investigation->value,
                'summary' => 'Promoted from monitoring finding',
            ]);

        $case = ComplianceCase::where('customer_id', $finding->subject_id)->first();
        $this->assertNotNull($case, 'A compliance case should be created from the finding');
        $response->assertRedirect(route('compliance.cases.show', $case));

        $finding->refresh();
        $this->assertSame(FindingStatus::CaseCreated, $finding->status);
    }

    #[Test]
    public function a_case_cannot_be_created_from_a_dismissed_finding(): void
    {
        $finding = ComplianceFinding::factory()->create(['status' => FindingStatus::Dismissed]);

        $this->actingAs($this->officer)
            ->post(route('compliance.findings.create-case', $finding->id), [
                'case_type' => ComplianceCaseType::Investigation->value,
            ])
            ->assertRedirect(route('compliance.findings.index'))
            ->assertSessionHas('error', 'A case cannot be created from this finding');

        $this->assertSame(0, ComplianceCase::count());
    }

    #[Test]
    public function escalate_moves_an_open_case_to_escalated(): void
    {
        $case = ComplianceCase::factory()->create(['status' => ComplianceCaseStatus::Open]);

        $this->actingAs($this->officer)
            ->post(route('compliance.cases.escalate', $case))
            ->assertRedirect()
            ->assertSessionHas('success', 'Case escalated successfully');

        $case->refresh();
        $this->assertSame(ComplianceCaseStatus::Escalated, $case->status);
        $this->assertNotNull($case->escalated_at);
    }

    #[Test]
    public function edd_document_upload_via_signed_link_marks_the_request_received(): void
    {
        Storage::fake();
        $record = EnhancedDiligenceRecord::factory()->create();
        $request = EddDocumentRequest::factory()->create([
            'edd_record_id' => $record->id,
            'status' => EddDocumentStatus::Pending->value,
        ]);

        $url = URL::signedRoute('compliance.edd.customer.upload', [
            'eddDocumentRequest' => $request->id,
            'customer_id' => $record->customer_id,
        ]);

        // The portal group sits inside the auth middleware stack, so the
        // upload is performed by a signed-in staff member with the signed
        // customer link.
        $this->actingAs($this->officer)
            ->post($url, ['file' => UploadedFile::fake()->image('passport.jpg')])
            ->assertRedirect()
            ->assertSessionHas('success', 'Document uploaded successfully.');

        $request->refresh();
        $this->assertSame(EddDocumentStatus::Received, $request->status, 'The document request must be marked received');
        $this->assertNotNull($request->file_path);
        $this->assertNotNull($request->uploaded_at);
    }

    #[Test]
    public function edd_document_upload_rejects_an_unsigned_link(): void
    {
        Storage::fake();
        $record = EnhancedDiligenceRecord::factory()->create();
        $request = EddDocumentRequest::factory()->create([
            'edd_record_id' => $record->id,
            'status' => EddDocumentStatus::Pending->value,
        ]);

        $this->actingAs($this->officer)
            ->post(route('compliance.edd.customer.upload', [
                'eddDocumentRequest' => $request->id,
                'customer_id' => $record->customer_id,
            ]), ['file' => UploadedFile::fake()->image('passport.jpg')])
            ->assertForbidden();

        $this->assertSame(EddDocumentStatus::Pending, $request->refresh()->status);
    }

    #[Test]
    public function risk_dashboard_rescreen_recalculates_the_customer_score(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->officer)
            ->post(route('compliance.risk-dashboard.rescreen'), ['customer_id' => $customer->id])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('risk_score_snapshots', ['customer_id' => $customer->id]);
    }

    #[Test]
    public function customer_can_be_closed_with_a_reason_when_nothing_is_blocking(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $customer = Customer::factory()->create();

        $this->actingAs($manager)
            ->post(route('customers.close', $customer), ['reason' => 'Customer requested account closure'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNotNull($customer->refresh()->closed_at, 'The customer must be marked closed');
    }

    #[Test]
    public function customer_closure_is_blocked_while_transactions_are_pending(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $customer = Customer::factory()->create();

        // A pending-approval transaction blocks closure.
        Transaction::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'pending_approval',
        ]);

        $this->actingAs($manager)
            ->post(route('customers.close', $customer), ['reason' => 'Customer requested account closure'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNull($customer->refresh()->closed_at, 'Closure must be blocked by the pending transaction');
    }
}
