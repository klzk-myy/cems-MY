<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmScreeningMatchRequest;
use App\Http\Requests\DismissScreeningMatchRequest;
use App\Models\Compliance\SanctionEntry;
use App\Models\Compliance\ScreeningResult;
use App\Models\Customer;
use App\Services\AuditService;
use App\Services\Screening\CustomerScreeningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    public function confirm(ConfirmScreeningMatchRequest $request, int $resultId): RedirectResponse
    {
        $validated = $request->validated();

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

        // Serialize the disposition on the result row lock: two concurrent
        // confirms (or a confirm racing a dismiss) must not both pass the
        // pending check — enforcement raises a regulatory FIU alert per call,
        // so a double-confirm is a duplicated reporting obligation, not just
        // a lost update.
        $decision = DB::transaction(function () use ($result, $customer, $validated) {
            $locked = ScreeningResult::whereKey($result->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                return null;
            }

            // Disposition inside the lock — writing it after the lock is
            // released would reopen the double-confirm window.
            $locked->markDispositioned('confirmed', $validated['reason'], (int) auth()->id());

            if ($locked->isAdverseMedia()) {
                // Adverse media confirmations escalate for review only - the
                // customer is not frozen, blocked or rejected.
                $outcome = $this->screeningService->handleConfirmedAdverseMatch(
                    $customer,
                    $locked->adverseMediaEntry === null ? 'medium' : $locked->adverseMediaEntry->severity
                );

                return [$outcome, 'adverse_media', $locked->adverseMediaEntry?->severity, $locked];
            }

            $listType = $locked->sanctionEntry?->sanctionList?->list_type->value ?? 'sanctions';
            $outcome = $this->screeningService->handleConfirmedMatch($customer, $listType);

            return [$outcome, $listType, null, $locked];
        });

        if ($decision === null) {
            return redirect()->route('compliance.screening.matches.show', $result->id)
                ->with('error', 'Screening result has already been dispositioned');
        }

        [$outcome, $listType, $severity, $result] = $decision;

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

    public function dismiss(DismissScreeningMatchRequest $request, int $resultId): RedirectResponse
    {
        $validated = $request->validated();

        $result = ScreeningResult::query()->findOrFail($resultId);

        if (! $result->isPending()) {
            return redirect()->route('compliance.screening.matches.show', $result->id)
                ->with('error', 'Screening result has already been dispositioned');
        }

        // Lock the result row for the pending check + disposition, then the
        // customer row for the restore probe — enforcement takes the same
        // customer lock, so a restore can never interleave with a confirm
        // landing on another result for this customer. Lock order is always
        // screening_results → customers on both paths.
        $dispositioned = DB::transaction(function () use ($result, $validated) {
            $locked = ScreeningResult::whereKey($result->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                return null;
            }

            $locked->markDispositioned('dismissed', $validated['reason'], (int) auth()->id());

            // If this dismissal removes the last real match and the customer
            // was flagged/deactivated by screening alone, restore their standing.
            $customer = $locked->customer;

            if ($customer !== null) {
                $lockedCustomer = Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();

                if ($lockedCustomer->sanction_hit
                    && ! $lockedCustomer->is_frozen
                    && ! $lockedCustomer->transactions_blocked
                    && ! ScreeningResult::where('customer_id', $lockedCustomer->id)
                        ->where('id', '!=', $locked->id)
                        ->withHits()
                        ->where(function ($query) {
                            $query->whereNull('disposition')
                                ->orWhere('disposition', 'confirmed');
                        })
                        ->exists()
                ) {
                    $lockedCustomer->sanction_hit = false;

                    if (! $lockedCustomer->is_active && $lockedCustomer->rejection_reason === null) {
                        $lockedCustomer->is_active = true;
                    }

                    $lockedCustomer->save();
                }
            }

            return $locked;
        });

        if ($dispositioned === null) {
            return redirect()->route('compliance.screening.matches.show', $result->id)
                ->with('error', 'Screening result has already been dispositioned');
        }

        $result = $dispositioned;

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
