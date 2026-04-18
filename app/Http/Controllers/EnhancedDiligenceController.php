<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\EnhancedDiligenceRecord;
use App\Models\FlaggedTransaction;
use App\Services\EddService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class EnhancedDiligenceController extends Controller
{
    protected EddService $eddService;

    public function __construct(EddService $eddService)
    {
        $this->eddService = $eddService;
    }

    public function index(Request $request)
    {
        $query = EnhancedDiligenceRecord::with(['customer', 'reviewer', 'flaggedTransaction']);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->risk_level) {
            $query->where('risk_level', $request->risk_level);
        }

        $records = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('compliance.edd.index', compact('records'));
    }

    public function create(Request $request)
    {
        $flagId = $request->flagged_transaction_id;
        $flag = null;
        $customer = null;

        if ($flagId) {
            $flag = FlaggedTransaction::findOrFail($flagId);
            $customer = $flag->customer;
        }

        $customers = Customer::where('risk_rating', 'High')->orWhere('pep_status', true)->get();

        return view('compliance.edd.create', compact('flag', 'customer', 'customers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'flagged_transaction_id' => 'nullable|exists:flagged_transactions,id',
            'risk_level' => 'required|in:Low,Medium,High',
            'source_of_funds' => 'required|string',
            'source_of_funds_description' => 'nullable|string',
            'purpose_of_transaction' => 'required|string',
            'business_justification' => 'nullable|string',
            'employment_status' => 'nullable|string',
            'employer_name' => 'nullable|string|max:200',
            'employer_address' => 'nullable|string|max:500',
            'annual_income_range' => 'nullable|string',
            'estimated_net_worth' => 'nullable|string',
            'source_of_wealth' => 'nullable|string',
            'source_of_wealth_description' => 'nullable|string',
            'additional_information' => 'nullable|string',
        ]);

        $flag = null;
        if ($request->flagged_transaction_id) {
            $flag = FlaggedTransaction::find($request->flagged_transaction_id);
        }

        $record = $this->eddService->createEddRecord($flag ?? new FlaggedTransaction(['customer_id' => $validated['customer_id']]), [
            'risk_level' => $validated['risk_level'],
        ]);

        $record = $this->eddService->updateEddRecord($record, $validated);

        return redirect()->route('compliance.edd.show', $record)
            ->with('success', 'EDD record created successfully.');
    }

    public function show(EnhancedDiligenceRecord $record)
    {
        $record->load(['customer', 'reviewer', 'flaggedTransaction']);

        return view('compliance.edd.show', compact('record'));
    }

    public function edit(EnhancedDiligenceRecord $record)
    {
        if (! $record->isComplete()) {
            return redirect()->route('compliance.edd.show', $record)
                ->with('error', 'Cannot edit a pending review or approved record.');
        }

        $record->load(['customer', 'flaggedTransaction']);

        return view('compliance.edd.edit', compact('record'));
    }

    public function update(Request $request, EnhancedDiligenceRecord $record)
    {
        if ($record->status === 'Approved') {
            return redirect()->back()->with('error', 'Cannot update an approved EDD record.');
        }

        $validated = $request->validate([
            'source_of_funds' => 'required|string',
            'source_of_funds_description' => 'nullable|string',
            'purpose_of_transaction' => 'required|string',
            'business_justification' => 'nullable|string',
            'employment_status' => 'nullable|string',
            'employer_name' => 'nullable|string|max:200',
            'employer_address' => 'nullable|string|max:500',
            'annual_income_range' => 'nullable|string',
            'estimated_net_worth' => 'nullable|string',
            'source_of_wealth' => 'nullable|string',
            'source_of_wealth_description' => 'nullable|string',
            'additional_information' => 'nullable|string',
        ]);

        $record = $this->eddService->updateEddRecord($record, $validated);

        return redirect()->route('compliance.edd.show', $record)
            ->with('success', 'EDD record updated successfully.');
    }

    public function submitReview(EnhancedDiligenceRecord $record)
    {
        try {
            $record = $this->eddService->submitForReview($record);

            return redirect()->back()->with('success', 'EDD record submitted for review.');
        } catch (\InvalidArgumentException $e) {
            Log::warning('EDD submitReview failed', ['exception' => $e, 'record_id' => $record->id]);
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function approve(Request $request, EnhancedDiligenceRecord $record)
    {
        if (! $record->isPendingReview()) {
            return redirect()->back()->with('error', 'Only pending records can be approved.');
        }

        $record = $this->eddService->approve($record, Auth::user(), $request->notes);

        return redirect()->back()->with('success', 'EDD record approved.');
    }

    public function reject(Request $request, EnhancedDiligenceRecord $record)
    {
        if (! $record->isPendingReview()) {
            return redirect()->back()->with('error', 'Only pending records can be rejected.');
        }

        $request->validate(['reason' => 'required|string']);

        $record = $this->eddService->reject($record, Auth::user(), $request->reason);

        return redirect()->back()->with('success', 'EDD record rejected.');
    }
}
