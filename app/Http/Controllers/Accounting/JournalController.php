<?php

namespace App\Http\Controllers\Accounting;

use App\Enums\UserRole;
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

        $user = auth()->user();

        $entries = JournalEntry::with(['lines', 'postedBy', 'creator', 'approver'])
            ->when(
                ! $user->isAdmin() && $user->role !== UserRole::Accountant,
                fn ($q) => $q->where(fn ($q2) => $q2->where('branch_id', $user->branch_id)->orWhereNull('branch_id'))
            )
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

        // Branch journals are stamped with the poster's own branch; admins may
        // post a company-wide (null) or branch-scoped entry.
        $user = $request->user();
        $branchId = $user->isAdmin()
            ? ($validated['branch_id'] ?? null)
            : $user->branch_id;

        try {
            $entry = $this->accountingService->createJournalEntry(
                $validated['lines'],
                'Manual',
                null,
                $validated['description'],
                $validated['entry_date'],
                null,
                $branchId
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
