<?php

namespace App\Http\Controllers;

use App\Models\JournalEntry;
use App\Services\AuditService;
use App\Services\JournalEntryWorkflowService;
use Illuminate\Http\Request;

class JournalEntryWorkflowController extends Controller
{
    protected JournalEntryWorkflowService $workflowService;

    public function __construct(
        JournalEntryWorkflowService $workflowService,
        protected AuditService $auditService
    ) {
        $this->workflowService = $workflowService;
    }

    protected function requireManagerOrAdmin(): void
    {
        if (! auth()->user()->isManager()) {
            abort(403, 'Unauthorized. Manager or Admin access required.');
        }
    }

    /**
     * Display workflow dashboard with pending entries.
     */
    public function workflow(Request $request)
    {
        $this->requireManagerOrAdmin();

        // Get pending entries for approval
        $pendingEntries = JournalEntry::where('status', 'Pending')
            ->with(['creator', 'lines.account'])
            ->orderBy('entry_date', 'desc')
            ->paginate(20);

        // Get recent activity (posted/rejected entries)
        $recentActivity = JournalEntry::whereIn('status', ['Posted', 'Reversed'])
            ->with(['creator', 'approver'])
            ->orderBy('updated_at', 'desc')
            ->limit(20)
            ->get();

        return view('accounting.journal.workflow', compact('pendingEntries', 'recentActivity'));
    }

    /**
     * Submit a draft entry for approval.
     */
    public function submit(JournalEntry $entry, Request $request)
    {
        try {
            $entry = $this->workflowService->submitForApproval($entry);
            $this->auditService->logJournalWorkflowEvent('journal_entry_submitted', $entry->id, [
                'new' => ['submitted_by' => auth()->user()->username],
            ]);

            return redirect()->back()->with('success', 'Entry submitted for approval.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Approve or reject a pending entry.
     */
    public function approve(JournalEntry $entry, Request $request)
    {
        $this->requireManagerOrAdmin();

        $action = $request->input('action', 'approve');
        $notes = $request->input('notes');

        try {
            if ($action === 'reject') {
                if (empty($notes)) {
                    return redirect()->back()->with('error', 'Rejection notes are required.');
                }
                $entry = $this->workflowService->reject($entry, $notes);
                $this->auditService->logJournalWorkflowEvent('journal_entry_rejected', $entry->id, [
                    'new' => ['rejected_by' => auth()->user()->username, 'notes' => $notes],
                ]);

                return redirect()->back()->with('success', 'Entry rejected and returned to draft.');
            } else {
                $entry = $this->workflowService->approve($entry, $notes);
                $this->auditService->logJournalWorkflowEvent('journal_entry_approved', $entry->id, [
                    'new' => ['approved_by' => auth()->user()->username, 'notes' => $notes],
                ]);

                return redirect()->back()->with('success', 'Entry approved and posted to ledger.');
            }
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
