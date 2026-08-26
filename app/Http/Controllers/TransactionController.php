<?php

namespace App\Http\Controllers;

use App\Enums\TransactionConfirmationStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Exceptions\Domain\DomainException;
use App\Exceptions\Domain\TransactionBlockedException;
use App\Http\Concerns\BranchScopedQuery;
use App\Http\Requests\ExportTransactionRequest;
use App\Http\Requests\IndexTransactionRequest;
use App\Http\Requests\StoreTransactionRequest;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Contracts\TransactionCreationServiceInterface;
use App\Services\Reporting\TransactionExportService;
use App\Services\Transaction\ReceiptGenerationService;
use App\Services\Transaction\TransactionCancellationService;
use App\Services\Transaction\TransactionConfirmationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TransactionController extends Controller
{
    use BranchScopedQuery;

    public function __construct(
        protected TransactionCreationServiceInterface $creationService,
        protected TransactionCancellationService $cancellationService,
        protected ReceiptGenerationService $receiptService,
        protected TransactionExportService $transactionExportService,
        protected TransactionConfirmationService $confirmationService,
    ) {}

    /**
     * Display a paginated list of transactions.
     *
     * Non-admin users can only see transactions for their own branch.
     */
    public function index(IndexTransactionRequest $request): View
    {
        $this->authorize('viewAny', Transaction::class);

        $validated = $request->validated();

        $query = Transaction::with(['journalEntry', 'deferredJournalEntry'])
            ->when($validated['search'] ?? null, function ($q, string $search) {
                // `reference` is a computed accessor (TX-00000123), so it cannot
                // be searched in SQL. Search by the numeric part of the reference
                // (the row id) and fall back to a purpose match.
                $referenceId = (int) preg_replace('/\D/', '', $search);

                return $q->where(function ($query) use ($search, $referenceId) {
                    $query->where('id', $referenceId > 0 ? $referenceId : 0)
                        ->orWhere('purpose', 'like', "%{$search}%");
                });
            })
            ->when($validated['status'] ?? null, function ($q, string $status) {
                return $q->where('status', $status);
            })
            ->when($validated['customer_id'] ?? null, function ($q, int $customerId) {
                return $q->where('customer_id', $customerId);
            });

        $this->scopeByBranch($query);

        $transactions = $query->orderBy('created_at', 'desc')->paginate(50)->withQueryString();

        return view('pages.transactions.index', compact('transactions'));
    }

    /**
     * Show the form to create a new transaction.
     *
     * Non-admin users can only select tills at their own branch.
     */
    public function create(): View
    {
        $this->authorize('create', Transaction::class);

        $currencies = Currency::select('code', 'name')->where('is_active', true)->get()->pluck('name', 'code');
        $customers = Customer::orderBy('full_name')->pluck('full_name', 'id');
        $branches = Branch::select('id', 'name')->orderBy('name')->get();
        $counters = Counter::where('status', 'active')->orderBy('name')->pluck('name', 'id');

        $suggested_rate = null;

        $tillQuery = TillBalance::where('date', today())
            ->whereNull('closed_at')
            ->with('currency');

        /** @var User|null $user */
        $user = auth()->user();
        if ($user && $user->branch_id !== null) {
            $tillQuery->where('branch_id', $user->branch_id);
        }
        $tillBalances = $tillQuery->get();

        return view('pages.transactions.create', compact('currencies', 'customers', 'tillBalances', 'branches', 'counters', 'suggested_rate'));
    }

    /**
     * Store a new transaction.
     *
     * The till ID is derived from the selected counter for backward compatibility.
     * XSS protection is handled by Blade's automatic escaping on output.
     */
    public function store(StoreTransactionRequest $request): RedirectResponse
    {
        $this->authorize('create', Transaction::class);

        $validated = $request->validated();
        $ipAddress = $request->ip();

        /** @var Counter|null $counter */
        $counter = Counter::find($validated['counter_id']);
        $validated['till_id'] = $counter ? (string) $counter->code : (string) $validated['counter_id'];

        try {
            $transaction = $this->creationService->prepareAndCreate($validated, (int) auth()->id(), $ipAddress);

            if ($transaction->status === TransactionStatus::PendingApproval) {
                return redirect()->route('transactions.show', $transaction)
                    ->with('warning', 'Transaction created and pending manager approval.');
            }

            return redirect()->route('transactions.show', $transaction)
                ->with('success', 'Transaction completed successfully. Receipt #'.$transaction->id);
        } catch (TransactionBlockedException $e) {
            return back()->with('error', 'Transaction blocked due to compliance restrictions. Please contact support.')->withInput();
        } catch (DomainException $e) {
            return back()->with('error', 'Transaction failed validation. Please check your input and try again.')->withInput();
        } catch (\Exception $e) {
            Log::error('Transaction creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
            ]);

            return back()->with('error', 'Transaction failed. Please contact support if the problem persists.')->withInput();
        }
    }

    /**
     * Display a single transaction.
     */
    public function show(Transaction $transaction): View
    {
        $this->authorize('view', $transaction);

        $transaction->load(['customer', 'user', 'approver', 'flags']);

        // Surface the confirmation gate to approvers: a PendingApproval deal
        // that requires manager confirmation but has no Confirmed record yet
        // cannot be approved and must be routed through the confirmation flow.
        $requiresManagerConfirmation = $transaction->status === TransactionStatus::PendingApproval
            && $this->confirmationService->requiresConfirmation($transaction)
            && ! $transaction->confirmations()
                ->where('status', TransactionConfirmationStatus::Confirmed->value)
                ->exists();

        return view('transactions.show', compact('transaction', 'requiresManagerConfirmation'));
    }

    /**
     * Display the cancellation form for a transaction.
     *
     * Only managers and admins can cancel transactions. The transaction must be
     * eligible for cancellation, within the cancellation window, not already
     * cancelled, and not reversed.
     */
    public function showCancel(Transaction $transaction): View|RedirectResponse
    {
        $user = auth()->user();

        if ($response = $this->ensureCanShowCancel($transaction, $user)) {
            return $response;
        }

        $transaction->load(['customer', 'user', 'approver', 'flags']);

        return view('transactions.cancel', compact('transaction'));
    }

    /**
     * Generate a PDF receipt for a completed transaction.
     *
     * Receipts can only be generated for completed transactions.
     */
    public function receipt(Transaction $transaction): RedirectResponse|Response
    {
        // Enforce the same branch-isolation rule as TransactionController::show:
        // without this, any authenticated user could download a PII-bearing PDF
        // receipt for any completed transaction in any branch by ID.
        $this->authorize('view', $transaction);

        if ($response = $this->ensureCanGenerateReceipt($transaction)) {
            return $response;
        }

        return $this->receiptService->generate($transaction);
    }

    /**
     * Ensure the transaction can be shown for cancellation.
     *
     * Only managers and admins may access the cancellation form. The transaction
     * must be eligible for cancellation, within the window, not already
     * cancelled, and not reversed.
     */
    private function ensureCanShowCancel(Transaction $transaction, User $user): ?RedirectResponse
    {
        if (! $user->isManager()) {
            abort(403, 'Only managers and admins can cancel transactions.');
        }

        if (! $this->cancellationService->canCancel($transaction)) {
            return back()->with('error', 'This transaction cannot be cancelled.');
        }

        if (! $this->cancellationService->isWithinCancellationWindow($transaction)) {
            return back()->with('error', 'This transaction is outside the cancellation window.');
        }

        if ($transaction->cancelled_at !== null) {
            return back()->with('error', 'This transaction has already been cancelled.');
        }

        if ($transaction->status->isReversed()) {
            return back()->with('error', 'Reversed transactions cannot be cancelled.');
        }

        return null;
    }

    /**
     * Ensure a receipt can be generated for the transaction.
     *
     * Receipts are only generated for completed transactions.
     */
    private function ensureCanGenerateReceipt(Transaction $transaction): ?RedirectResponse
    {
        if (! $transaction->status->isCompleted()) {
            return back()->with('error', 'Receipts can only be generated for completed transactions.');
        }

        return null;
    }

    /**
     * Show the export form.
     */
    public function exportForm(): View
    {
        return view('transactions.export', [
            'branches' => Branch::all(),
            'types' => TransactionType::cases(),
        ]);
    }

    /**
     * Export transactions as CSV.
     */
    public function export(ExportTransactionRequest $request): BinaryFileResponse
    {
        $filePath = $this->transactionExportService->exportTransactions($request->validated(), (int) auth()->id());

        return response()->download($filePath);
    }
}
