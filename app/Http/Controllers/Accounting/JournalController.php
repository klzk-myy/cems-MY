<?php

namespace App\Http\Controllers\Accounting;

use App\Exceptions\Domain\AccountingPeriodException;
use App\Exceptions\Domain\DomainException;
use App\Http\Controllers\Concerns\ResolvesBranchScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\JournalIndexRequest;
use App\Http\Requests\Accounting\ReverseJournalEntryRequest;
use App\Http\Requests\Accounting\StoreJournalEntryRequest;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Services\Accounting\AccountingService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class JournalController extends Controller
{
    use ResolvesBranchScope;

    public function __construct(
        protected AccountingService $accountingService,
    ) {}

    public function index(JournalIndexRequest $request): View
    {
        $this->authorize('viewAny', JournalEntry::class);

        $user = auth()->user();

        $entries = JournalEntry::with(['lines', 'postedBy', 'creator', 'approver'])
            ->when(
                ! $user->role->canManageAllBranches(),
                fn ($q) => $q->where(fn ($q2) => $q2->where('branch_id', $user->branch_id)->orWhereNull('branch_id'))
            )
            ->when(
                $request->filled('search'),
                fn ($q) => $q->where(fn ($q2) => $q2
                    ->where('description', 'like', '%'.$request->string('search')->value().'%')
                    ->orWhere('entry_number', 'like', '%'.$request->string('search')->value().'%'))
            )
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->string('status')->value())
            )
            ->when(
                $request->filled('date'),
                fn ($q) => $q->whereDate('entry_date', Carbon::parse($request->string('date')->value())->toDateString())
            )
            ->orderBy('entry_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(25)
            ->withQueryString();

        return view('accounting.journal.index', compact('entries'));
    }

    public function create(): View
    {
        $this->authorize('create', JournalEntry::class);

        $accounts = ChartOfAccount::where('is_active', true)
            ->where('allow_journal', true)
            ->orderBy('account_code')
            ->get();

        $branches = auth()->user()->role->canManageAllBranches()
            ? Branch::where('is_active', true)->orderBy('code')->pluck('name', 'id')
            : collect();

        return view('accounting.journal.create', compact('accounts', 'branches'));
    }

    public function store(StoreJournalEntryRequest $request): RedirectResponse
    {
        $this->authorize('create', JournalEntry::class);

        $validated = $request->validated();

        // Branch journals are stamped with the poster's own branch;
        // cross-branch roles may post a company-wide (null) or
        // branch-scoped entry. An unassigned branch user 403s rather than
        // silently writing to the company book.
        $branchId = $this->resolveReportBranchId($request->user(), $request);

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

        $entry->load('lines.account', 'postedBy', 'reversedBy', 'creator', 'approver', 'branch');

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

        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Journal reversal failed', ['exception' => $e, 'entry_id' => $entry->id]);

            return back()->with('error', 'Reversal failed. Please try again.');
        }
    }
}
