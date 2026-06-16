<?php

namespace App\Services;

use App\Enums\CounterSessionStatus;
use App\Enums\TellerAllocationStatus;
use App\Exceptions\Domain\BranchClosingChecklistIncompleteException;
use App\Models\Branch;
use App\Models\BranchClosureWorkflow;
use App\Models\BranchPool;
use App\Models\CounterSession;
use App\Models\TellerAllocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BranchClosingService
{
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

        return [
            'counters_closed' => $this->checkCountersClosed($branch),
            'allocations_returned' => $this->checkAllocationsReturned($branch),
            'transfers_complete' => $this->checkTransfersComplete($branch),
            'documents_finalized' => $this->checkDocumentsFinalized($branch, $workflow),
        ];
    }

    public function canFinalize(BranchClosureWorkflow $workflow): bool
    {
        $checklist = $this->getChecklist($workflow);

        return $checklist['counters_closed']
            && $checklist['allocations_returned']
            && $checklist['transfers_complete']
            && $checklist['documents_finalized'];
    }

    public function finalize(BranchClosureWorkflow $workflow, User $finalizer): void
    {
        if (! $this->canFinalize($workflow)) {
            throw new BranchClosingChecklistIncompleteException;
        }

        $workflow->update([
            'status' => 'finalized',
            'finalized_at' => now(),
        ]);
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
            $branch = $workflow->branch;

            // Return all active allocations to branch pool
            $activeAllocations = TellerAllocation::query()
                ->with(['counter', 'user', 'branch'])
                ->where('branch_id', $branch->id)
                ->where('status', TellerAllocationStatus::ACTIVE->value)
                ->get();

            foreach ($activeAllocations as $allocation) {
                $tellerAllocationService = app(TellerAllocationService::class);
                $tellerAllocationService->returnToPool($allocation);
            }

            // Create settlement journal entries (transfer balances to HQ)
            $this->createSettlementJournalEntries($branch, $settler);

            // Log the settlement action
            $auditService = app(AuditService::class);
            $auditService->log(
                'branch_settled',
                $settler->id,
                'BranchClosureWorkflow',
                $workflow->id,
                [],
                [
                    'branch_id' => $branch->id,
                    'branch_code' => $branch->code,
                    'allocations_returned' => $activeAllocations->count(),
                    'action' => 'branch_closed_and_settled',
                ]
            );

            $workflow->update([
                'status' => 'settled',
                'settlement_at' => now(),
            ]);
        });
    }

    /**
     * Create settlement journal entries for branch closing.
     * Transfers remaining balances from branch pool to headquarters.
     */
    protected function createSettlementJournalEntries(Branch $branch, User $settler): void
    {
        // Get all branch pool balances
        $branchPools = BranchPool::where('branch_id', $branch->id)->get();

        foreach ($branchPools as $pool) {
            if ($pool->available_balance > 0) {
                $accountingService = app(AccountingService::class);
                $lines = [
                    [
                        'account_code' => $pool->currency_code === 'MYR' ? '1000' : '1100',
                        'debit' => '0.00',
                        'credit' => $pool->available_balance,
                        'description' => "Branch {$branch->code} pool balance",
                    ],
                    [
                        'account_code' => '9000', // HQ suspense account
                        'debit' => $pool->available_balance,
                        'credit' => '0.00',
                        'description' => "HQ receiving {$pool->currency_code} from branch {$branch->code}",
                    ],
                ];
                $accountingService->createJournalEntry(
                    $lines,
                    'BranchSettlement',
                    null,
                    "Branch {$branch->code} settlement - {$pool->currency_code} transfer to HQ",
                    now()->toDateString(),
                    $settler->id
                );
            }
        }
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

    protected function checkTransfersComplete(Branch $branch): bool
    {
        $pendingTransfers = TellerAllocation::where('branch_id', $branch->id)
            ->whereIn('status', [
                TellerAllocationStatus::PENDING->value,
                TellerAllocationStatus::APPROVED->value,
            ])
            ->count();

        return $pendingTransfers === 0;
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
