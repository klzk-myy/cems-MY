<?php

namespace App\Http\Controllers\Compliance;

use App\Enums\StrStatus;
use App\Http\Controllers\Controller;
use App\Models\Compliance\ComplianceCase;
use App\Models\StrDraft;
use App\Models\Transaction;
use App\Services\StrReportService;
use App\Services\ThresholdService;
use Illuminate\Http\Request;

class StrStudioController extends Controller
{
    public function __construct(
        protected StrReportService $strReportService,
        protected ThresholdService $thresholdService
    ) {}

    public function index()
    {
        $drafts = StrDraft::with(['customer', 'case'])
            ->orderByDesc('created_at')
            ->paginate(25);

        $summary = $this->strReportService->getFilingDeadlineSummary();

        return view('compliance.str-studio.index', compact('drafts', 'summary'));
    }

    public function create(int $caseId)
    {
        $case = ComplianceCase::with(['customer', 'alerts'])->findOrFail($caseId);

        return view('compliance.str-studio.create', compact('case'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'case_id' => 'nullable|exists:compliance_cases,id',
            'narrative' => 'required|string',
            'suspected_activity' => 'nullable|string',
        ]);

        $draft = StrDraft::create([
            'customer_id' => $request->customer_id,
            'case_id' => $request->case_id,
            'narrative' => $request->narrative,
            'suspected_activity' => $request->suspected_activity,
            'status' => StrStatus::Draft,
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('compliance.str-studio.show', $draft->id)
            ->with('success', 'STR draft created successfully');
    }

    public function show(StrDraft $draft)
    {
        $draft->load(['customer', 'case', 'case.alerts']);

        return view('compliance.str-studio.show', compact('draft'));
    }

    public function generateNarrative(Request $request, StrDraft $draft)
    {
        $draft->load(['case.alerts']);

        $alertTypes = $draft->case?->alerts->pluck('type')->toArray() ?? [];
        $transactionPatterns = [];

        if ($draft->case?->alerts) {
            $transactionIds = [];
            foreach ($draft->case->alerts as $alert) {
                if ($alert->flaggedTransaction?->transaction_id) {
                    $transactionIds[] = $alert->flaggedTransaction->transaction_id;
                }
            }
            if (! empty($transactionIds)) {
                $transactions = Transaction::whereIn('id', $transactionIds)->get();
                $transactionPatterns = [
                    'total_amount' => $transactions->sum('amount_local'),
                    'max_amount' => $transactions->max('amount_local'),
                    'sub_threshold_count' => $transactions->where('amount_local', '<', $this->thresholdService->getStrThreshold())->count(),
                ];
            }
        }

        $narrative = $this->strReportService->suggestNarrative($alertTypes, $transactionPatterns);

        $draft->update(['narrative' => $narrative]);

        return redirect()->back()->with('success', 'Narrative generated successfully');
    }

    public function submit(StrDraft $draft)
    {
        $draft->update(['status' => StrStatus::PendingReview]);

        return redirect()->back()->with('success', 'STR draft submitted for review');
    }

    public function convert(StrDraft $draft)
    {
        if (! $draft->canConvert()) {
            return redirect()->back()->with('error', 'STR draft cannot be converted');
        }

        $strReport = $this->strReportService->convertToStrReport($draft);

        return redirect()->route('str.show', $strReport->id)
            ->with('success', 'STR draft converted to formal report');
    }

    public function deadlines()
    {
        $deadlines = $this->strReportService->getFilingDeadlineSummary();
        $drafts = StrDraft::pending()
            ->whereNotNull('filing_deadline')
            ->orderBy('filing_deadline')
            ->get();

        return view('compliance.str-studio.deadlines', compact('drafts', 'deadlines'));
    }
}
