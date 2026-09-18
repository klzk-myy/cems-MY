<?php

namespace Tests\Feature\Compliance;

use App\Enums\ComplianceCaseStatus;
use App\Enums\StrReportStatus;
use App\Enums\UserRole;
use App\Exceptions\Domain\CaseManagementException;
use App\Models\Alert;
use App\Models\Compliance\ComplianceCase;
use App\Models\Customer;
use App\Models\FlaggedTransaction;
use App\Models\StrReport;
use App\Models\Transaction;
use App\Models\User;
use App\Policies\StrReportPolicy;
use App\Services\Compliance\StrReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StrReportTest extends TestCase
{
    use RefreshDatabase;

    private User $officer;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->officer = User::factory()->create(['role' => 'compliance_officer']);
        $this->customer = Customer::factory()->create();
    }

    /**
     * Build a closed case whose linked flagged transactions carry the given
     * MYR amounts (one flag + alert per amount).
     */
    private function makeClosedCase(array $amounts): ComplianceCase
    {
        $case = ComplianceCase::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => ComplianceCaseStatus::Closed,
            'resolved_at' => now(),
            'assigned_to' => $this->officer->id,
        ]);

        foreach ($amounts as $amount) {
            $flag = FlaggedTransaction::factory()->create([
                'customer_id' => $this->customer->id,
                'transaction_id' => Transaction::factory()->create([
                    'customer_id' => $this->customer->id,
                    'amount_local' => $amount,
                ])->id,
                'status' => 'open',
            ]);

            Alert::factory()->create([
                'case_id' => $case->id,
                'customer_id' => $this->customer->id,
                'flagged_transaction_id' => $flag->id,
                'reason' => 'Aggregate threshold breach',
            ]);
        }

        return $case;
    }

    #[Test]
    public function create_from_case_drafts_report_when_aggregate_meets_threshold(): void
    {
        $service = app(StrReportService::class);

        // 30000 + 25000 = 55000 >= 50000
        $case = $this->makeClosedCase([30000, 25000]);

        $report = $service->createFromCase($case, $this->officer);

        $this->assertInstanceOf(StrReport::class, $report);
        $this->assertTrue($report->status === StrReportStatus::Draft);
        $this->assertSame('55000.0000', (string) $report->trigger_amount);
        $this->assertSame($case->id, $report->case_id);
        $this->assertSame($this->customer->id, $report->customer_id);
        $this->assertNull($report->bnm_reference);
        $this->assertNull($report->submitted_at);
    }

    #[Test]
    public function create_from_case_rejects_below_threshold(): void
    {
        $service = app(StrReportService::class);

        // 20000 + 29999.9999 = 49999.9999 < 50000
        $case = $this->makeClosedCase([20000, 29999.9999]);

        $this->expectException(CaseManagementException::class);
        $service->createFromCase($case, $this->officer);
    }

    #[Test]
    public function submit_and_acknowledge_transition_the_lifecycle(): void
    {
        $service = app(StrReportService::class);
        $case = $this->makeClosedCase([50000]);
        $report = $service->createFromCase($case, $this->officer);

        $service->submit($report, 'BNM-REF-0001', $this->officer);
        $report = $report->fresh();

        $this->assertTrue($report->status === StrReportStatus::Submitted);
        $this->assertSame('BNM-REF-0001', $report->bnm_reference);
        $this->assertNotNull($report->submitted_at);

        $service->acknowledge($report, $this->officer);
        $report = $report->fresh();

        $this->assertTrue($report->status === StrReportStatus::Acknowledged);
        $this->assertNotNull($report->acknowledged_at);
        $this->assertTrue($report->status->isTerminal());

        // Each lifecycle transition wrote an audit entry.
        $this->assertDatabaseHas('system_logs', ['action' => 'str_report_drafted']);
        $this->assertDatabaseHas('system_logs', ['action' => 'str_report_submitted']);
        $this->assertDatabaseHas('system_logs', ['action' => 'str_report_acknowledged']);
    }

    #[Test]
    public function auto_draft_creates_report_on_closure_and_never_throws_below_threshold(): void
    {
        $service = app(StrReportService::class);

        $above = $this->makeClosedCase([60000]);
        $created = $service->autoDraftForClosedCase($above);
        $this->assertNotNull($created);
        $this->assertTrue($created->refresh()->status === StrReportStatus::Draft);

        // Second call is a silent no-op: one report per case.
        $this->assertNull($service->autoDraftForClosedCase($above));
        $this->assertSame(1, StrReport::where('case_id', $above->id)->count());

        // Below threshold: silently skipped.
        $below = $this->makeClosedCase([1000]);
        $this->assertNull($service->autoDraftForClosedCase($below));
    }

    #[Test]
    public function policy_restricts_str_access_to_compliance_and_admin(): void
    {
        $officer = User::factory()->create(['role' => 'compliance_officer']);
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $teller = User::factory()->create(['role' => 'teller']);

        $report = new StrReport;

        $this->assertTrue($officer->can('viewAny', $report));
        $this->assertTrue($officer->can('update', $report));
        $this->assertFalse($teller->can('viewAny', $report));
        $this->assertTrue($admin->can('viewAny', $report));
        $this->assertTrue($admin->can('update', $report));

        // Policy is auto-discovered by convention (no AuthServiceProvider
        // registration) for App\Models\StrReport.
        $this->assertInstanceOf(StrReportPolicy::class, Gate::getPolicyFor($report));
    }
}
