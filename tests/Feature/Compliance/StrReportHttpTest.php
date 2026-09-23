<?php

namespace Tests\Feature\Compliance;

use App\Enums\ComplianceCaseStatus;
use App\Enums\StrReportStatus;
use App\Enums\UserRole;
use App\Models\Compliance\Alert;
use App\Models\Compliance\ComplianceCase;
use App\Models\Compliance\FlaggedTransaction;
use App\Models\Compliance\StrReport;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Compliance\StrReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * STR filing HTTP surface (pd-00 s22): the PATCH submit/acknowledge routes
 * behind the BNM reporting lifecycle. The service-level lifecycle is
 * covered by StrReportTest; these tests pin the web routes — authorization,
 * validation, and the flash/redirect contract.
 */
class StrReportHttpTest extends TestCase
{
    use RefreshDatabase;

    private User $officer;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->officer = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->customer = Customer::factory()->create();
    }

    #[Test]
    public function submit_transitions_a_draft_to_submitted_with_the_bnm_reference(): void
    {
        $report = $this->draftReport();

        $this->actingAs($this->officer)
            ->patch(route('compliance.str.submit', $report), ['bnm_reference' => 'BNM-REF-0001'])
            ->assertRedirect()
            ->assertSessionHas('success', 'STR submitted to BNM.');

        $report->refresh();
        $this->assertSame(StrReportStatus::Submitted, $report->status);
        $this->assertSame('BNM-REF-0001', $report->bnm_reference);
        $this->assertNotNull($report->submitted_at);
    }

    #[Test]
    public function acknowledge_transitions_a_submitted_report_to_acknowledged(): void
    {
        $report = $this->draftReport();
        app(StrReportService::class)->submit($report, 'BNM-REF-0002', $this->officer);

        $this->actingAs($this->officer)
            ->patch(route('compliance.str.acknowledge', $report))
            ->assertRedirect()
            ->assertSessionHas('success', 'STR acknowledged by BNM.');

        $report->refresh();
        $this->assertSame(StrReportStatus::Acknowledged, $report->status);
        $this->assertNotNull($report->acknowledged_at);
    }

    #[Test]
    public function submit_requires_a_bnm_reference(): void
    {
        $report = $this->draftReport();

        $this->actingAs($this->officer)
            ->patch(route('compliance.str.submit', $report), ['bnm_reference' => ''])
            ->assertRedirect()
            ->assertSessionHasErrors('bnm_reference');

        $report->refresh();
        $this->assertSame(StrReportStatus::Draft, $report->status, 'A failed submit must leave the draft untouched');
    }

    #[Test]
    public function teller_cannot_submit_or_acknowledge_str_reports(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $report = $this->draftReport();

        $this->actingAs($teller)
            ->patch(route('compliance.str.submit', $report), ['bnm_reference' => 'BNM-REF-X'])
            ->assertForbidden();

        $this->actingAs($teller)
            ->patch(route('compliance.str.acknowledge', $report))
            ->assertForbidden();

        $this->assertSame(StrReportStatus::Draft, $report->refresh()->status);
    }

    /**
     * A Draft STR over the RM 50k aggregate threshold, built the same way
     * the automatic case-closure listener produces one.
     */
    private function draftReport(): StrReport
    {
        $case = ComplianceCase::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => ComplianceCaseStatus::Closed,
            'resolved_at' => now(),
            'assigned_to' => $this->officer->id,
        ]);

        $flag = FlaggedTransaction::factory()->create([
            'customer_id' => $this->customer->id,
            'transaction_id' => Transaction::factory()->create([
                'customer_id' => $this->customer->id,
                'amount_myr' => '55000',
            ])->id,
            'status' => 'open',
        ]);

        Alert::factory()->create([
            'case_id' => $case->id,
            'customer_id' => $this->customer->id,
            'flagged_transaction_id' => $flag->id,
            'reason' => 'Aggregate threshold breach',
        ]);

        return app(StrReportService::class)->createFromCase($case, $this->officer);
    }
}
