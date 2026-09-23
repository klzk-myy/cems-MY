<?php

namespace App\Http\Controllers\Compliance;

use App\Enums\ApprovalStatus;
use App\Enums\Permission;
use App\Exceptions\Domain\PepApprovalRequiredException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApprovePepApprovalRequest;
use App\Http\Requests\RejectPepApprovalRequest;
use App\Models\PepApprovalRequest;
use App\Services\Compliance\PepApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Head-office Senior Management decisions on PEP relationship approvals.
 *
 * Per pd-00.md 14C.13.1(d) a PEP customer (foreign PEPs always, domestic
 * higher-risk PEPs) may not transact until Senior Management approves the
 * business relationship. TransactionValidationService raises
 * PepApprovalRequiredException and creates the pending request; this
 * controller is the approval path that unblocks (or permanently rejects)
 * it. Decisions are restricted to managers/admins and audit-logged by the
 * service with a tamper-evident chain entry.
 */
class PepApprovalController extends Controller
{
    public function __construct(
        protected PepApprovalService $pepApprovalService
    ) {}

    /**
     * List pending PEP approval requests (manager/admin queue).
     */
    public function index(Request $request): View
    {
        $this->requirePermission(Permission::AccessCompliance);

        $pending = PepApprovalRequest::with(['customer'])
            ->where('status', ApprovalStatus::Pending->value)
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('compliance.pep.index', compact('pending'));
    }

    /**
     * Approve a pending PEP relationship request.
     */
    public function approve(ApprovePepApprovalRequest $request, PepApprovalRequest $pepApproval): RedirectResponse
    {
        $this->requirePermission(Permission::AccessCompliance);

        try {
            $this->pepApprovalService->approve($pepApproval, $request->user());
        } catch (PepApprovalRequiredException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'PEP relationship approved. The customer may now proceed with transactions.');
    }

    /**
     * Reject a pending PEP relationship request.
     */
    public function reject(RejectPepApprovalRequest $request, PepApprovalRequest $pepApproval): RedirectResponse
    {
        $this->requirePermission(Permission::AccessCompliance);

        try {
            $this->pepApprovalService->reject($pepApproval, $request->user(), $request->validated('reason'));
        } catch (PepApprovalRequiredException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'PEP relationship request rejected.');
    }
}
