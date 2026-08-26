<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use App\Models\SanctionEntry;
use App\Models\ScreeningResult;
use App\Services\AuditService;
use App\Services\CustomerScreeningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScreeningMatchController extends Controller
{
    public function __construct(
        protected CustomerScreeningService $screeningService,
        protected AuditService $auditService,
    ) {}

    public function index(Request $request): View
    {
        $query = ScreeningResult::query()
            ->withHits()
            ->pending()
            ->with(['customer', 'sanctionEntry.sanctionList', 'adverseMediaEntry']);

        if ($request->filled('status') && $request->string('status')->toString() !== 'all') {
            $query->where('result', $request->string('status'));
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', (int) $request->input('customer_id'));
        }

        $results = $query->orderByDesc('match_score')->orderByDesc('created_at')->paginate(25)->withQueryString();

        return view('compliance.screening.matches.index', [
            'results' => $results,
            'sanctionsLoaded' => SanctionEntry::exists(),
        ]);
    }

    public function show(int $resultId): View
    {
        $result = ScreeningResult::query()
            ->with(['customer', 'sanctionEntry.sanctionList', 'adverseMediaEntry', 'transaction'])
            ->findOrFail($resultId);

        return view('compliance.screening.matches.show', [
            'result' => $result,
        ]);
    }

    public function confirm(Request $request, int $resultId): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $result = ScreeningResult::query()
            ->with(['customer', 'sanctionEntry.sanctionList', 'adverseMediaEntry'])
            ->findOrFail($resultId);

        $customer = $result->customer;

        if ($customer === null) {
            return redirect()->route('compliance.screening.matches.index')
                ->with('error', 'Screening result has no associated customer');
        }

        if (! $result->isPending()) {
            return redirect()->route('compliance.screening.matches.show', $result->id)
                ->with('error', 'Screening result has already been dispositioned');
        }

        if ($result->isAdverseMedia()) {
            // Adverse media confirmations escalate for review only - the
            // customer is not frozen, blocked or rejected.
            $listType = 'adverse_media';
            $severity = $result->adverseMediaEntry->severity ?? 'medium';

            $outcome = $this->screeningService->handleConfirmedAdverseMatch($customer, $severity);
        } else {
            $listType = $result->sanctionEntry?->sanctionList?->list_type->value ?? 'sanctions';
            $severity = null;

            $outcome = $this->screeningService->handleConfirmedMatch($customer, $listType);
        }

        $result->markDispositioned('confirmed', $validated['reason'], (int) auth()->id());

        $this->auditService->logSanctionEvent('screening_match_confirmed', $result->id, [
            'entity_type' => 'ScreeningResult',
            'customer_id' => $customer->id,
            'list_type' => $listType,
            'severity' => $severity,
            'match_score' => $result->match_score,
            'outcome' => $outcome,
            'reason' => $validated['reason'],
            'decided_by' => (int) auth()->id(),
        ]);

        return redirect()->route('compliance.screening.matches.index')
            ->with('success', "Match confirmed for {$customer->full_name}."
                .($result->isAdverseMedia()
                    ? ' Escalated for enhanced due diligence review.'
                    : ' Customer frozen, transactions blocked and FIU reporting flagged.'));
    }

    public function dismiss(Request $request, int $resultId): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $result = ScreeningResult::query()->findOrFail($resultId);

        if (! $result->isPending()) {
            return redirect()->route('compliance.screening.matches.show', $result->id)
                ->with('error', 'Screening result has already been dispositioned');
        }

        $result->markDispositioned('dismissed', $validated['reason'], (int) auth()->id());

        $this->auditService->logSanctionEvent('screening_match_dismissed', $result->id, [
            'entity_type' => 'ScreeningResult',
            'customer_id' => $result->customer_id,
            'match_score' => $result->match_score,
            'reason' => $validated['reason'],
            'decided_by' => (int) auth()->id(),
        ]);

        return redirect()->route('compliance.screening.matches.index')
            ->with('success', 'Screening match dismissed');
    }
}
