<?php

namespace Tests\Unit\Services\Compliance;

use App\Enums\AlertPriority;
use App\Enums\CaseResolution;
use App\Enums\ComplianceCasePriority;
use App\Enums\ComplianceCaseStatus;
use App\Enums\ComplianceCaseType;
use App\Enums\FindingSeverity;
use App\Enums\FlagStatus;
use App\Events\CaseOpened;
use App\Exceptions\Domain\CaseManagementException;
use App\Models\Alert;
use App\Models\Compliance\ComplianceCase;
use App\Models\Customer;
use App\Models\FlaggedTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\ComplianceCaseAssignedNotification;
use App\Services\Compliance\CaseManagementService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CaseManagementServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected CaseManagementService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CaseManagementService::class);
    }

    #[Test]
    public function create_from_alerts_persists_a_complete_case(): void
    {
        Event::fake([CaseOpened::class]);

        $customer = Customer::factory()->create();
        $officer = User::factory()->create();

        $alerts = collect([80, 65])->map(fn (int $score) => Alert::factory()->create([
            'customer_id' => $customer->id,
            'risk_score' => $score,
        ]));

        $case = $this->service->createFromAlerts($alerts->pluck('id')->all(), $officer->id);

        $this->assertMatchesRegularExpression('/^CASE-\d{4}-\d{5}$/', $case->case_number);
        $this->assertSame(ComplianceCaseType::Investigation, $case->case_type);
        $this->assertSame(ComplianceCaseStatus::Open, $case->status);
        $this->assertSame(FindingSeverity::Critical, $case->severity);
        $this->assertSame(ComplianceCasePriority::Critical, $case->priority);
        $this->assertSame($customer->id, $case->customer_id);
        $this->assertSame($officer->id, $case->assigned_to);
        $this->assertSame('Manual', $case->created_via);
        $this->assertNotNull($case->sla_deadline);
        $this->assertSame(2, $case->alerts()->count());
        $this->assertSame($case->id, $alerts->first()->fresh()->case_id);
    }

    #[Test]
    public function create_from_alerts_rejects_alerts_for_multiple_customers(): void
    {
        $alertA = Alert::factory()->create();
        $alertB = Alert::factory()->create();

        $this->expectException(CaseManagementException::class);

        $this->service->createFromAlerts([$alertA->id, $alertB->id], User::factory()->create()->id);

        $this->assertSame(0, ComplianceCase::count());
    }

    #[Test]
    public function link_alert_to_case_rejects_cross_customer_alert(): void
    {
        $case = ComplianceCase::factory()->create();
        $alert = Alert::factory()->create();

        $this->expectException(CaseManagementException::class);

        $this->service->linkAlertToCase($alert, $case);
    }

    #[Test]
    public function update_status_enforces_transition_rules(): void
    {
        $case = ComplianceCase::factory()->create(['status' => ComplianceCaseStatus::Open]);

        $closed = $this->service->updateStatus($case, ComplianceCaseStatus::Closed);

        $this->assertSame(ComplianceCaseStatus::Closed, $closed->status);
        $this->assertNotNull($closed->resolved_at);

        $this->expectException(CaseManagementException::class);

        // Closed is terminal: reopening must be rejected.
        $this->service->updateStatus($closed, ComplianceCaseStatus::Open);
    }

    #[Test]
    public function update_status_is_a_no_op_for_the_same_status(): void
    {
        $case = ComplianceCase::factory()->create(['status' => ComplianceCaseStatus::Open]);

        $updated = $this->service->updateStatus($case, ComplianceCaseStatus::Open);

        $this->assertSame(ComplianceCaseStatus::Open, $updated->status);
    }

    #[Test]
    public function close_case_auto_drafts_str_when_aggregate_meets_threshold(): void
    {
        $customer = Customer::factory()->create();
        $officer = User::factory()->create();

        $flag = FlaggedTransaction::factory()->create([
            'customer_id' => $customer->id,
            'transaction_id' => Transaction::factory()->create([
                'customer_id' => $customer->id,
                'amount_myr' => 60000,
            ])->id,
            'status' => 'open',
        ]);

        $case = ComplianceCase::factory()->create([
            'customer_id' => $customer->id,
            'assigned_to' => $officer->id,
            'status' => ComplianceCaseStatus::UnderReview,
        ]);

        Alert::factory()->create([
            'case_id' => $case->id,
            'customer_id' => $customer->id,
            'flagged_transaction_id' => $flag->id,
            'status' => FlagStatus::Resolved,
        ]);

        $closed = $this->service->closeCase($case, CaseResolution::NoConcern, 'cleared');

        $this->assertSame(ComplianceCaseStatus::Closed, $closed->status);
        $this->assertNotNull($closed->resolved_at);
        $this->assertDatabaseHas('str_reports', ['case_id' => $case->id]);
    }

    #[Test]
    public function close_case_refuses_unresolved_alerts(): void
    {
        $customer = Customer::factory()->create();
        $case = ComplianceCase::factory()->create([
            'customer_id' => $customer->id,
            'status' => ComplianceCaseStatus::UnderReview,
        ]);

        Alert::factory()->create([
            'case_id' => $case->id,
            'customer_id' => $customer->id,
            'status' => FlagStatus::Open,
        ]);

        $this->expectException(CaseManagementException::class);

        $this->service->closeCase($case, CaseResolution::NoConcern);
    }

    #[Test]
    public function close_case_refuses_reclose_and_escalate_refuses_closed_source(): void
    {
        $closed = ComplianceCase::factory()->create(['status' => ComplianceCaseStatus::Closed]);

        try {
            $this->service->closeCase($closed, CaseResolution::NoConcern);
            $this->fail('closeCase on a Closed case must throw');
        } catch (CaseManagementException) {
        }

        $this->expectException(CaseManagementException::class);

        $this->service->escalateCase($closed);
    }

    #[Test]
    public function derive_priority_from_alerts_maps_highest_alert_priority(): void
    {
        $customer = Customer::factory()->create();
        $case = ComplianceCase::factory()->create(['customer_id' => $customer->id]);

        Alert::factory()->create(['customer_id' => $customer->id, 'case_id' => $case->id, 'priority' => AlertPriority::Low, 'risk_score' => 10]);
        Alert::factory()->create(['customer_id' => $customer->id, 'case_id' => $case->id, 'priority' => AlertPriority::High, 'risk_score' => 65]);
        Alert::factory()->create(['customer_id' => $customer->id, 'case_id' => $case->id, 'priority' => AlertPriority::Critical, 'risk_score' => 90]);

        $this->assertSame(ComplianceCasePriority::Critical, $case->derivePriorityFromAlerts());
    }

    #[Test]
    public function derive_priority_from_alerts_returns_medium_when_no_alerts(): void
    {
        $case = ComplianceCase::factory()->create();

        $this->assertSame(ComplianceCasePriority::Medium, $case->derivePriorityFromAlerts());
    }

    #[Test]
    public function sla_hours_are_consistent_across_creation_paths(): void
    {
        $this->assertSame(120, ComplianceCase::slaHoursFor(FindingSeverity::Medium));
        $this->assertSame(240, ComplianceCase::slaHoursFor(FindingSeverity::Low));

        $manual = $this->service->createManualCase(
            ComplianceCaseType::Investigation,
            Customer::factory()->create()->id,
            User::factory()->create()->id,
            FindingSeverity::Medium
        );

        // createManualCase previously threw an Error because calculateSlaDeadline
        // referenced a non-existent ComplianceCaseType::Str case.
        $this->assertEqualsWithDelta(now()->addHours(120)->timestamp, $manual->sla_deadline->timestamp, 60);

        $urgent = $this->service->createManualCase(
            ComplianceCaseType::SanctionReview,
            Customer::factory()->create()->id,
            User::factory()->create()->id,
            FindingSeverity::High
        );

        $this->assertEqualsWithDelta(now()->addHours(24)->timestamp, $urgent->sla_deadline->timestamp, 60);
    }

    #[Test]
    public function case_summary_counts_priority_buckets_by_titlecase_values(): void
    {
        $customer = Customer::factory()->create();
        ComplianceCase::factory()->count(2)->create([
            'customer_id' => $customer->id,
            'status' => ComplianceCaseStatus::Open,
            'priority' => ComplianceCasePriority::Critical,
        ]);
        ComplianceCase::factory()->create([
            'customer_id' => $customer->id,
            'status' => ComplianceCaseStatus::Open,
            'priority' => ComplianceCasePriority::Low,
        ]);

        $summary = $this->service->getCaseSummary();

        $this->assertSame(3, $summary['total_open']);
        $this->assertSame(2, $summary['critical']);
        $this->assertSame(1, $summary['low']);
    }

    #[Test]
    public function open_cases_are_ordered_by_priority(): void
    {
        $customer = Customer::factory()->create();
        $low = ComplianceCase::factory()->create([
            'customer_id' => $customer->id,
            'status' => ComplianceCaseStatus::Open,
            'priority' => ComplianceCasePriority::Low,
        ]);
        $high = ComplianceCase::factory()->create([
            'customer_id' => $customer->id,
            'status' => ComplianceCaseStatus::Open,
            'priority' => ComplianceCasePriority::High,
        ]);

        $cases = $this->service->getOpenCases();

        $this->assertSame($high->id, $cases->first()->id);
        $this->assertSame($low->id, $cases->last()->id);
    }

    #[Test]
    public function case_number_is_generated_when_not_provided(): void
    {
        $case = ComplianceCase::create([
            'case_type' => ComplianceCaseType::Investigation,
            'status' => ComplianceCaseStatus::Open,
            'severity' => FindingSeverity::Medium,
            'priority' => ComplianceCasePriority::Medium,
            'customer_id' => Customer::factory()->create()->id,
            'assigned_to' => User::factory()->create()->id,
            'created_via' => 'Manual',
            'sla_deadline' => now()->addDay(),
        ]);

        $this->assertMatchesRegularExpression('/^CASE-\d{4}-\d{5}$/', $case->case_number);
    }

    #[Test]
    public function assigning_case_notifies_new_assignee_with_deadline(): void
    {
        Notification::fake();

        $currentAssignee = User::factory()->create()->id;
        $newAssignee = User::factory()->complianceOfficer()->create();

        $case = ComplianceCase::factory()->create([
            'assigned_to' => $currentAssignee,
            'sla_deadline' => now()->addDays(3),
        ]);

        $this->service->assignToOfficer($case, (int) $newAssignee->id);

        Notification::assertSentTo(
            $newAssignee,
            ComplianceCaseAssignedNotification::class,
            function (ComplianceCaseAssignedNotification $notification) use ($newAssignee): bool {
                $payload = $notification->toArray($newAssignee);

                return $payload['days_until_deadline'] !== null
                    && abs((float) $payload['days_until_deadline'] - 3.0) < 0.01;
            }
        );
    }

    #[Test]
    public function reassigning_the_same_officer_does_not_notify_again(): void
    {
        Notification::fake();

        $assignee = User::factory()->complianceOfficer()->create();

        $case = ComplianceCase::factory()->create([
            'assigned_to' => $assignee->id,
            'sla_deadline' => now()->addDay(),
        ]);

        $this->service->assignToOfficer($case, (int) $assignee->id);
        $this->service->assignToOfficer($case, (int) $assignee->id);

        Notification::assertNotSentTo($assignee, ComplianceCaseAssignedNotification::class);
    }
}
