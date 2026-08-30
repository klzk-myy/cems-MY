<?php

namespace App\Http\Controllers\Accounting;

use App\Exceptions\Domain\AccountingPeriodException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\ReverseJournalEntryRequest;
use App\Http\Requests\Accounting\StoreJournalEntryRequest;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Services\Accounting\AccountingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class JournalController extends Controller
{
    public function __construct(
        protected AccountingService $accountingService,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', JournalEntry::class);

        $entries = JournalEntry::with(['lines', 'postedBy', 'creator', 'approver'])
            ->orderBy('entry_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(25);

        return view('accounting.journal.index', compact('entries'));
    }

    public function create(): View
    {
        $this->authorize('create', JournalEntry::class);

        $accounts = ChartOfAccount::where('is_active', true)
            ->orderBy('account_code')
            ->get();

        return view('accounting.journal.create', compact('accounts'));
    }

    public function store(StoreJournalEntryRequest $request): RedirectResponse
    {
        $this->authorize('create', JournalEntry::class);

        $validated = $request->validated();

        try {
            $entry = $this->accountingService->createJournalEntry(
                $validated['lines'],
                'Manual',
                null,
                $validated['description'],
                $validated['entry_date']
            );

            return redirect()->route('accounting.journal.show', $entry)
                ->with('success', 'Journal entry created successfully.');

        } catch (AccountingPeriodException $e) {
            Log::warning('JournalEntry create failed', ['exception' => $e, 'description' => $request->input('description')]);

            return back()->withInput()->withErrors(['lines' => $e->getMessage()]);
        }
    }

    public function show(JournalEntry $entry): View
    {
        $this->authorize('view', $entry);

        $entry->load('lines.account', 'postedBy', 'reversedBy');

        return view('accounting.journal.show', compact('entry'));
    }

    public function reverse(ReverseJournalEntryRequest $request, JournalEntry $entry): RedirectResponse
    {
        $this->authorize('reverse', $entry);

        if ($entry->isReversed()) {
            return back()->with('error', 'Entry is already reversed.');
        }

        $validated = $request->validated();

        try {
            $reversal = $this->accountingService->reverseJournalEntry(
                $entry,
                $validated['reason']
            );

            return redirect()->route('accounting.journal.show', $reversal)
                ->with('success', 'Entry reversed successfully.');

        } catch (\Exception $e) {
            return back()->with('error', 'Reversal failed. Please try again.');
        }
    }
}
