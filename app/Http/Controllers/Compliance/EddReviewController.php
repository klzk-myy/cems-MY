<?php

namespace App\Http\Controllers\Compliance;

use App\Enums\EddStatus;
use App\Exceptions\Domain\EddValidationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\RejectEddReviewRequest;
use App\Models\Compliance\EnhancedDiligenceRecord;
use App\Services\Compliance\EddService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * Staff-facing EDD review surface.
 *
 * Approve/reject delegate to EddService, which re-checks the
 * finalisable-status gate under a row lock — the same path the API
 * controller uses.
 */
class EddReviewController extends Controller
{
    public function __construct(
        protected EddService $eddService
    ) {}

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

        try {
            $this->eddService->approve($eddRecord, $request->user());
        } catch (EddValidationException $e) {
            return back()->with('error', $e->getMessage());
        }

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

        $validated = $request->validated();

        try {
            $this->eddService->reject($eddRecord, $request->user(), $validated['reason']);
        } catch (EddValidationException $e) {
            return back()->with('error', $e->getMessage());
        }

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
     * rejected). Kept identical to the API controller's gate via EddService.
     *
     * @return array<int, EddStatus>
     */
    private function finalisableStatuses(): array
    {
        return $this->eddService->finalisableStatuses();
    }
}
