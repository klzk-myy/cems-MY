<?php

namespace App\Http\Controllers\Compliance;

use App\Enums\EddStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\RejectEddReviewRequest;
use App\Models\EnhancedDiligenceRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * Staff-facing EDD review surface.
 *
 * Mirrors Api/V1/Compliance/EddController approve/reject logic exactly,
 * including the finalisable-status gate: only QuestionnaireSubmitted and
 * PendingReview records may be approved or rejected.
 */
class EddReviewController extends Controller
{
    /**
     * List pending EDD reviews.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', EnhancedDiligenceRecord::class);

        $status = $request->get('status');

        $query = EnhancedDiligenceRecord::with(['customer', 'flaggedTransaction']);

        if ($status !== null && EddStatus::tryFrom((string) $status) !== null) {
            $query->where('status', $status);
        } else {
            // Default view: work awaiting a reviewer decision.
            $query->whereIn('status', $this->finalisableStatuses());
        }

        $records = $query->orderByDesc('created_at')->paginate(20);

        $portalUrls = $records->getCollection()
            ->mapWithKeys(fn (EnhancedDiligenceRecord $record) => [
                $record->id => $this->customerPortalUrl($record),
            ])
            ->all();

        return view('compliance.edd.reviews.index', compact('records', 'portalUrls'));
    }

    /**
     * Show a single EDD record for review.
     */
    public function show(EnhancedDiligenceRecord $eddRecord): View
    {
        $this->authorize('view', $eddRecord);

        $eddRecord->load(['customer', 'flaggedTransaction']);

        return view('compliance.edd.reviews.show', [
            'record' => $eddRecord,
            'isFinalisable' => in_array($eddRecord->status, $this->finalisableStatuses(), true),
            'portalUrl' => $this->customerPortalUrl($eddRecord),
        ]);
    }

    /**
     * Approve an EDD record.
     */
    public function approve(Request $request, EnhancedDiligenceRecord $eddRecord): RedirectResponse
    {
        $this->authorize('update', $eddRecord);

        if (! in_array($eddRecord->status, $this->finalisableStatuses(), true)) {
            return back()->with(
                'error',
                'Only records with a submitted questionnaire or in Pending Review can be approved (current: '.$eddRecord->status->value.').'
            );
        }

        $eddRecord->update([
            'status' => EddStatus::Approved,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return redirect()
            ->route('compliance.edd-reviews.index')
            ->with('success', "EDD record {$eddRecord->edd_reference} approved.");
    }

    /**
     * Reject an EDD record. A reason is required and stored as review notes.
     */
    public function reject(RejectEddReviewRequest $request, EnhancedDiligenceRecord $eddRecord): RedirectResponse
    {
        $this->authorize('update', $eddRecord);

        if (! in_array($eddRecord->status, $this->finalisableStatuses(), true)) {
            return back()->with(
                'error',
                'Only records with a submitted questionnaire or in Pending Review can be rejected (current: '.$eddRecord->status->value.').'
            );
        }

        $validated = $request->validated();

        $eddRecord->update([
            'status' => EddStatus::Rejected,
            'review_notes' => $validated['reason'],
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return redirect()
            ->route('compliance.edd-reviews.index')
            ->with('success', "EDD record {$eddRecord->edd_reference} rejected.");
    }

    /**
     * Signed customer-portal URL for a record's customer (same portal the
     * customer received by email).
     */
    protected function customerPortalUrl(EnhancedDiligenceRecord $record): ?string
    {
        if (! $record->customer) {
            return null;
        }

        return URL::signedRoute('compliance.edd.customer.show', [
            'eddRecord' => $record->id,
            'customer_id' => $record->customer->id,
        ]);
    }

    /**
     * Statuses a record must be in before it can be finalised (approved or
     * rejected). Kept identical to the API controller's gate.
     *
     * @return array<int, EddStatus>
     */
    private function finalisableStatuses(): array
    {
        return [EddStatus::QuestionnaireSubmitted, EddStatus::PendingReview];
    }
}
