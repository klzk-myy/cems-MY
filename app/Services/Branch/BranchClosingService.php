<?php

namespace App\Services\Branch;

use App\Enums\BranchClosureStatus;
use App\Enums\CounterSessionStatus;
use App\Enums\TellerAllocationStatus;
use App\Exceptions\Domain\BranchClosingChecklistIncompleteException;
use App\Exceptions\Domain\InvalidStateException;
use App\Models\Branch;
use App\Models\BranchClosureWorkflow;
use App\Models\CounterSession;
use App\Models\TellerAllocation;
use App\Models\User;
use App\Services\AuditService;
use App\Services\EodReconciliationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BranchClosingService
{
    public function __construct(
        protected TellerAllocationService $tellerAllocationService,
        protected AuditService $auditService,
        protected EodReconciliationService $eodReconciliationService
    ) {}

    public function initiateClosure(Branch $branch, User $initiator): BranchClosureWorkflow
    {
        $workflow = BranchClosureWorkflow::create([
            'branch_id' => $branch->id,
            'initiated_by' => $initiator->id,
            'status' => 'initiated',
        ]);

        return $workflow;
    }

    public function getChecklist(BranchClosureWorkflow $workflow): array
    {
        $branch = $workflow->branch;

        if (! $branch instanceof Branch) {
            throw new \RuntimeException("Closure workflow {$workflow->id} has no branch assigned.");
        }

        return [
            'counters_closed' => $this->checkCountersClosed($branch),
            'allocations_returned' => $this->checkAllocationsReturned($branch),
            'documents_finalized' => $this->checkDocumentsFinalized($branch, $workflow),
        ];
    }

    public function canFinalize(BranchClosureWorkflow $workflow): bool
    {
        $checklist = $this->getChecklist($workflow);

        return $checklist['counters_closed']
            && $checklist['allocations_returned']
            && $checklist['documents_finalized'];
    }

    public function finalize(BranchClosureWorkflow $workflow, User $finalizer): void
    {
        DB::transaction(function () use ($workflow, $finalizer) {
            $lockedWorkflow = BranchClosureWorkflow::whereKey($workflow->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Idempotent: re-finalizing a finalized workflow is a no-op.
            if ($lockedWorkflow->status === BranchClosureStatus::Finalized) {
                return;
            }

            $checklist = $this->getChecklist($lockedWorkflow);

            if (! ($checklist['counters_closed']
                && $checklist['allocations_returned']
                && $checklist['documents_finalized'])) {
                throw new BranchClosingChecklistIncompleteException;
            }

            $branch = $lockedWorkflow->branch;

            // Archive the day's proof on the workflow row: the checklist that
            // passed plus a snapshot of the reconciliation the manager saw.
            $lockedWorkflow->update([
                'status' => 'finalized',
                'finalized_at' => now(),
                'checklist' => [
                    'results' => $checklist,
                    'recon' => $this->getDayReconciliation($branch),
                ],
            ]);

            $this->auditService->log(
                'branch_closure_finalized',
                $finalizer->id,
                'BranchClosureWorkflow',
                $lockedWorkflow->id,
                [],
                [
                    'branch_id' => $branch->id,
                    'branch_code' => $branch->code,
                    'business_date' => $lockedWorkflow->created_at?->toDateString(),
                ]
            );
        });
    }

    /**
     * Reopen a finalized day for corrections — a privileged, audited escape
     * hatch. Reverting to settled un-freezes the business date; the workflow
     * can be finalized again once corrections are posted.
     */
    public function reopen(BranchClosureWorkflow $workflow, User $user): void
    {
        DB::transaction(function () use ($workflow, $user) {
            $lockedWorkflow = BranchClosureWorkflow::whereKey($workflow->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedWorkflow->status !== BranchClosureStatus::Finalized) {
                throw new InvalidStateException(
                    "Closure workflow {$lockedWorkflow->id} cannot be reopened from status '{$lockedWorkflow->status->value}'."
                );
            }

            $lockedWorkflow->update([
                'status' => 'settled',
                'finalized_at' => null,
            ]);

            $this->auditService->log(
                'branch_closure_reopened',
                $user->id,
                'BranchClosureWorkflow',
                $lockedWorkflow->id,
                [],
                [
                    'branch_id' => $lockedWorkflow->branch_id,
                    'business_date' => $lockedWorkflow->created_at?->toDateString(),
                ]
            );
        });
    }

    /**
     * The latest finalized workflow for a branch — the candidate for reopen.
     */
    public function getLatestFinalizedWorkflow(Branch $branch): ?BranchClosureWorkflow
    {
        return BranchClosureWorkflow::where('branch_id', $branch->id)
            ->where('status', BranchClosureStatus::Finalized->value)
            ->latest('id')
            ->first();
    }

    /**
     * Today's reconciliation for the branch — sessions, expected-vs-counted
     * totals, and per-counter variances — trimmed to the lightweight payload
     * shown on the close page and archived at finalize.
     *
     * @return array{date: string, summary: array<string, int>, totals: array<string, string>, counters: array<int, array<string, mixed>>, large_transactions: int, flagged_transactions: int}
     */
    public function getDayReconciliation(Branch $branch): array
    {
        $recon = $this->eodReconciliationService->generateDailyReconciliationSummary(
            Carbon::today(),
            $branch->id
        );

        /** @var array<int, array<string, mixed>> $counterSummaries */
        $counterSummaries = $recon['counter_summaries'];

        return [
            'date' => $recon['date'],
            'summary' => $recon['summary'],
            'totals' => $recon['totals'],
            'counters' => array_map(fn (array $counter) => [
                'counter_code' => $counter['counter_code'],
                'counter_name' => $counter['counter_name'],
                'session_status' => $counter['session']['status'] ?? null,
                'opening_float' => $counter['opening_float'],
                'closing_float_expected' => $counter['closing_float_expected'],
                'closing_float_actual' => $counter['closing_float_actual'],
                'variance' => $counter['variance'],
            ], $counterSummaries),
            'large_transactions' => $recon['large_transactions']['count'],
            'flagged_transactions' => $recon['flagged_transactions']['count'],
        ];
    }

    public function getActiveWorkflow(Branch $branch): ?BranchClosureWorkflow
    {
        return BranchClosureWorkflow::where('branch_id', $branch->id)
            ->whereIn('status', ['initiated', 'settled'])
            ->latest()
            ->first();
    }

    public function settle(BranchClosureWorkflow $workflow, User $settler): void
    {
        DB::transaction(function () use ($workflow, $settler) {
            $lockedWorkflow = BranchClosureWorkflow::whereKey($workflow->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Idempotent: settling an already-settled workflow is a no-op.
            if ($lockedWorkflow->status === BranchClosureStatus::Settled) {
                return;
            }

            if ($lockedWorkflow->status !== BranchClosureStatus::Initiated) {
                throw new InvalidStateException(
                    "Closure workflow {$lockedWorkflow->id} cannot be settled from status '{$lockedWorkflow->status->value}'."
                );
            }

            $branch = $lockedWorkflow->branch;

            if (! $branch instanceof Branch) {
                throw new \RuntimeException("Closure workflow {$lockedWorkflow->id} has no branch assigned.");
            }

            // Settlement force-returns allocations to the branch pool; running
            // it over open counters would corrupt live sessions.
            if (! $this->checkCountersClosed($branch)) {
                throw new BranchClosingChecklistIncompleteException;
            }

            // Return all active allocations to branch pool
            $activeAllocations = TellerAllocation::query()
                ->with(['counter', 'user', 'branch'])
                ->where('branch_id', $branch->id)
                ->where('status', TellerAllocationStatus::ACTIVE->value)
                ->get();

            foreach ($activeAllocations as $allocation) {
                $this->tellerAllocationService->returnToPool($allocation);
            }

            // Stale pending/approved requests can never proceed past a
            // branch settlement — cancel them here rather than gating the
            // close on them. Approved rows release their pool earmark back to
            // available_balance.
            $cancelledRequests = TellerAllocation::query()
                ->where('branch_id', $branch->id)
                ->whereIn('status', [
                    TellerAllocationStatus::PENDING->value,
                    TellerAllocationStatus::APPROVED->value,
                ])
                ->get()
                ->each(fn (TellerAllocation $allocation) => $this->tellerAllocationService->cancelAllocation(
                    $allocation,
                    $settler,
                    'Cancelled at branch settlement'
                ))
                ->count();

            // Log the settlement action
            $this->auditService->log(
                'branch_settled',
                $settler->id,
                'BranchClosureWorkflow',
                $lockedWorkflow->id,
                [],
                [
                    'branch_id' => $branch->id,
                    'branch_code' => $branch->code,
                    'allocations_returned' => $activeAllocations->count(),
                    'requests_cancelled' => $cancelledRequests,
                    'action' => 'branch_closed_and_settled',
                ]
            );

            $lockedWorkflow->update([
                'status' => 'settled',
                'settlement_at' => now(),
            ]);
        });
    }

    protected function checkCountersClosed(Branch $branch): bool
    {
        $openSessions = CounterSession::whereHas('counter', function ($query) use ($branch) {
            $query->where('branch_id', $branch->id);
        })
            ->where('status', CounterSessionStatus::Open->value)
            ->count();

        return $openSessions === 0;
    }

    protected function checkAllocationsReturned(Branch $branch): bool
    {
        $activeAllocations = TellerAllocation::where('branch_id', $branch->id)
            ->where('status', TellerAllocationStatus::ACTIVE->value)
            ->count();

        return $activeAllocations === 0;
    }

    protected function checkDocumentsFinalized(Branch $branch, BranchClosureWorkflow $workflow): bool
    {
        $pendingWorkflows = BranchClosureWorkflow::where('branch_id', $branch->id)
            ->whereNotIn('status', ['finalized'])
            ->where('id', '!=', $workflow->id)
            ->count();

        return $pendingWorkflows === 0;
    }
}
