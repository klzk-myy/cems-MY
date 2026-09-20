<?php

namespace Tests\Http\Simulation\WaveA;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Http\Simulation\Support\SimulationTestCase;
use Tests\Http\Simulation\WaveA\Steps\AccountingSteps;
use Tests\Http\Simulation\WaveA\Steps\AlertSteps;
use Tests\Http\Simulation\WaveA\Steps\AllocationSteps;
use Tests\Http\Simulation\WaveA\Steps\ApprovalSteps;
use Tests\Http\Simulation\WaveA\Steps\AuditSteps;
use Tests\Http\Simulation\WaveA\Steps\AuthSteps;
use Tests\Http\Simulation\WaveA\Steps\BatchSteps;
use Tests\Http\Simulation\WaveA\Steps\BranchClosingSteps;
use Tests\Http\Simulation\WaveA\Steps\BranchPoolSteps;
use Tests\Http\Simulation\WaveA\Steps\BudgetSteps;
use Tests\Http\Simulation\WaveA\Steps\CancellationSteps;
use Tests\Http\Simulation\WaveA\Steps\CaseSteps;
use Tests\Http\Simulation\WaveA\Steps\ComplianceSteps;
use Tests\Http\Simulation\WaveA\Steps\CounterLifecycleSteps;
use Tests\Http\Simulation\WaveA\Steps\CounterSteps;
use Tests\Http\Simulation\WaveA\Steps\EddSteps;
use Tests\Http\Simulation\WaveA\Steps\EodSteps;
use Tests\Http\Simulation\WaveA\Steps\KycSteps;
use Tests\Http\Simulation\WaveA\Steps\MfaSteps;
use Tests\Http\Simulation\WaveA\Steps\NotificationSteps;
use Tests\Http\Simulation\WaveA\Steps\PepSteps;
use Tests\Http\Simulation\WaveA\Steps\PeriodSteps;
use Tests\Http\Simulation\WaveA\Steps\ReconciliationSteps;
use Tests\Http\Simulation\WaveA\Steps\SanctionsSteps;
use Tests\Http\Simulation\WaveA\Steps\ScheduleSteps;
use Tests\Http\Simulation\WaveA\Steps\StockTransferSteps;
use Tests\Http\Simulation\WaveA\Steps\StrSteps;
use Tests\Http\Simulation\WaveA\Steps\TransactionSteps;

/**
 * Wave A — full business-day sweep.
 *
 * Drives 32 steps across 12 domain groups, each step asserting data-flow
 * integrity at the domain joint. Steps A1-A11 run on both the web and API v1
 * surfaces; the remaining 21 run on the single surface that has a route.
 */
#[Group('wave-a')]
class BusinessDaySweepTest extends SimulationTestCase
{
    use AccountingSteps;
    use AlertSteps;
    use AllocationSteps;
    use ApprovalSteps;
    use AuditSteps;
    use AuthSteps;
    use BatchSteps;
    use BranchClosingSteps;
    use BranchPoolSteps;
    use BudgetSteps;
    use CancellationSteps;
    use CaseSteps;
    use ComplianceSteps;
    use CounterLifecycleSteps;
    use CounterSteps;
    use EddSteps;
    use EodSteps;
    use KycSteps;
    use MfaSteps;
    use NotificationSteps;
    use PepSteps;
    use PeriodSteps;
    use ReconciliationSteps;
    use SanctionsSteps;
    use ScheduleSteps;
    use StockTransferSteps;
    use StrSteps;
    use TransactionSteps;

    #[Test]
    public function it_runs_full_business_day_sweep(): void
    {
        // Paired steps are gated on the selected surface (`--surface=web|api|both`
        // via SIM_SURFACE). Single-surface steps always run; mixed steps gate
        // their API assertions internally (see AuthSteps).
        $this->itAuthenticates();                       // A1
        // A2 opens via the API opening-request + approve-and-open flow on
        // every surface selection — the web counter routes were removed in
        // the drawerless rework, and downstream steps need the session's
        // till balances regardless of which surface is under test.
        $this->itOpensCounter();                        // A2
        $this->runOnSurface('api', fn () => $this->itOpensCounterViaApi());                  // A2b
        $this->runOnSurface('web', fn () => $this->itViewsAllocations());                    // A3
        $this->runOnSurface('api', fn () => $this->itViewsAllocationsViaApi());              // A3b
        $this->runOnSurface('api', fn () => $this->itViewsBranchAllocationsViaApi());        // A3c
        $this->runOnSurface('web', fn () => $this->itBooksTransaction());                    // A4
        $this->runOnSurface('api', fn () => $this->itBooksTransactionViaApi());              // A4b
        $this->runOnSurface('web', fn () => $this->itApprovesTransaction($this->state->webTransactionId));  // A5
        $this->runOnSurface('api', fn () => $this->itApprovesTransactionViaApi($this->state->apiTransactionId)); // A5b
        $this->itRequestsCancellation($this->state->transactionId);  // A6
        $this->itApprovesCancellation($this->state->transactionId);  // A6c
        $this->itScreensCustomer();                     // A7
        $this->itListsAlerts();                         // A7b
        $this->runOnSurface('web', fn () => $this->itRunsEodReconciliation());             // A8
        $this->runOnSurface('api', fn () => $this->itRunsEodReconciliationViaApi());       // A8b
        $this->itRunsMonthEndReports();                 // A9
        $this->itClosesBranch();                        // A10
        $this->itManagesStockTransfers();               // A11
        // A12 counter handover removed: the handover initiate route was web-
        // only and was retired with the drawerless UI. Acknowledge coverage
        // lives in tests/Feature/CounterHandoverAcknowledgeTest.
        $this->itEmergencyClosesCounter();              // A13
        $this->itManagesBankReconciliation();           // A14
        $this->itManagesBudgets();                      // A15
        $this->itManagesPeriods();                      // A16
        $this->itClosesMonthEndViaApi();                // A17
        $this->itRunsRevaluation();                     // A18
        $this->itManagesEdd();                          // A19
        $this->itManagesCases();                        // A20
        $this->itManagesPepRequests();                  // A21
        $this->itFilesStr();                            // A22
        $this->itManagesSanctionsImport();              // A23
        $this->itManagesMfa();                          // A24
        $this->itManagesBatchImport();                  // A25
        $this->itManagesKyc();                          // A26
        $this->itManagesNotifications();                // A27
        $this->itManagesReportSchedules();              // A28
        $this->itManagesSystemAlerts();                 // A29
        $this->itManagesBranchPools();                  // A30
        $this->itViewsAuditLogs();                      // A31
        $this->itListsChartOfAccounts();                // A32
    }
}
