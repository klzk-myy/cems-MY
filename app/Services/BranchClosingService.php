<?php

namespace App\Services;

use App\Enums\CounterSessionStatus;
use App\Enums\TellerAllocationStatus;
use App\Exceptions\Domain\BranchClosingChecklistIncompleteException;
use App\Models\Branch;
use App\Models\BranchClosureWorkflow;
use App\Models\CounterSession;
use App\Models\TellerAllocation;
use App\Models\User;

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
        $workflow->update([
            'status' => 'settled',
            'settlement_at' => now(),
        ]);
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
