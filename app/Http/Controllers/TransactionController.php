<?php

namespace App\Http\Controllers;

use App\Enums\TransactionConfirmationStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Exceptions\Domain\DomainException;
use App\Exceptions\Domain\TransactionBlockedException;
use App\Http\Concerns\BranchScopedQuery;
use App\Http\Concerns\HandlesControllerErrors;
use App\Http\Concerns\MapsTransactionExceptionsToFields;
use App\Http\Requests\ExportTransactionRequest;
use App\Http\Requests\IndexTransactionRequest;
use App\Http\Requests\StoreTransactionRequest;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Contracts\TransactionCreationServiceInterface;
use App\Services\Customer\CustomerService;
use App\Services\Reporting\TransactionExportService;
use App\Services\ThresholdService;
use App\Services\Transaction\ReceiptGenerationService;
use App\Services\Transaction\TransactionCancellationService;
use App\Services\Transaction\TransactionConfirmationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TransactionController extends Controller
{
    use BranchScopedQuery, HandlesControllerErrors, MapsTransactionExceptionsToFields;

    public function __construct(
        protected TransactionCreationServiceInterface $creationService,
        protected TransactionCancellationService $cancellationService,
        protected ReceiptGenerationService $receiptService,
        protected TransactionExportService $transactionExportService,
        protected TransactionConfirmationService $confirmationService,
        protected CustomerService $customerService,
        protected ThresholdService $thresholdService,
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

        $query = Transaction::with(['journalEntry', 'deferredJournalEntry', 'customer:id,full_name'])
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
            ->when($validated['type'] ?? null, function ($q, string $type) {
                return $q->where('type', $type);
            })
            ->when($validated['currency_code'] ?? null, function ($q, string $currencyCode) {
                return $q->where('currency_code', $currencyCode);
            })
            ->when($validated['date_from'] ?? null, function ($q, string $dateFrom) {
                return $q->whereDate('created_at', '>=', $dateFrom);
            })
            ->when($validated['date_to'] ?? null, function ($q, string $dateTo) {
                return $q->whereDate('created_at', '<=', $dateTo);
            })
            ->when(isset($validated['is_refund']) && $validated['is_refund'] !== '', function ($q) use ($validated) {
                return $q->where('is_refund', (bool) $validated['is_refund']);
            })
            ->when($validated['customer_id'] ?? null, function ($q, int $customerId) {
                return $q->where('customer_id', $customerId);
            });

        $this->scopeByBranch($query);

        $transactions = $query->orderBy('created_at', 'desc')->paginate(50)->withQueryString();

        $statusOptions = Transaction::query()
            ->select('status')
            ->distinct()
            ->pluck('status')
            ->mapWithKeys(fn ($status) => [$status->value => $status->label()])
            ->toArray();

        $currencyOptions = Transaction::query()
            ->select('currency_code')
            ->distinct()
            ->orderBy('currency_code')
            ->pluck('currency_code', 'currency_code')
            ->toArray();

        $typeOptions = collect(TransactionType::cases())
            ->mapWithKeys(fn ($type) => [$type->value => $type->label()])
            ->toArray();

        return view('transactions.index', compact('transactions', 'statusOptions', 'currencyOptions', 'typeOptions'));
    }

    /**
     * Show the form to create a new transaction.
     *
     * Non-admin users can only select tills at their own branch.
     */
    public function create(): View
    {
        $this->authorize('create', Transaction::class);

        $activeCurrencies = Currency::where('is_active', true)->get(['code', 'name', 'rate_unit', 'rate_inverse']);
        $currencies = $activeCurrencies->pluck('name', 'code');
        $currencyUnits = $activeCurrencies->pluck('rate_unit', 'code');
        $currencyInverses = $activeCurrencies->pluck('rate_inverse', 'code');
        $branches = Branch::select('id', 'name')->orderBy('name')->get();
        $idempotencyKey = Str::uuid()->toString();

        $suggested_rate = null;

        /** @var User|null $user */
        $user = auth()->user();

        // CDD amount tiers drive which customer fields are required on the
        // form; the service re-checks them server-side once amount_myr is
        // computed exactly.
        $cddThresholds = [
            'specific' => (float) $this->thresholdService->getSpecificCddThreshold(),
            'standard' => (float) $this->thresholdService->getStandardCddThreshold(),
        ];

        $tillQuery = TillBalance::whereDate('date', today())
            ->whereNull('closed_at')
            ->with('currency');

        if ($user && $user->branch_id !== null) {
            $tillQuery->where('branch_id', $user->branch_id);
        }
        $tillBalances = $tillQuery->get();

        return view('transactions.create', compact('currencies', 'currencyUnits', 'currencyInverses', 'cddThresholds', 'tillBalances', 'branches', 'suggested_rate', 'idempotencyKey'));
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

        // The Form Request already mapped counter_id → till_id during
        // prepareForValidation; no additional mapping is needed here.

        try {
            $validated['customer_id'] = $this->customerService
                ->resolveForBooking($validated, (int) auth()->id())
                ->id;

            $transaction = $this->creationService->prepareAndCreate($validated, (int) auth()->id(), $ipAddress);

            if ($transaction->status === TransactionStatus::PendingApproval) {
                return redirect()->route('transactions.show', $transaction)
                    ->with('warning', 'Transaction created and pending manager approval.');
            }

            return redirect()->route('transactions.show', $transaction)
                ->with('success', 'Transaction completed successfully. Receipt #'.$transaction->id);
        } catch (TransactionBlockedException $e) {
            // Pin to the customer field but keep the message generic — the
            // specific compliance reason must not leak to the teller.
            return back()->withErrors([
                'customer_id' => 'Transaction blocked due to compliance restrictions. Please contact support.',
            ])->withInput();
        } catch (DomainException $e) {
            $field = $this->transactionExceptionField($e);

            return $field !== null
                ? back()->withErrors([$field => $e->getMessage()])->withInput()
                : back()->with('error', $e->getMessage())->withInput();
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return $this->handleExceptionWeb($e, 'Transaction creation failed', 'Transaction failed. Please contact support if the problem persists.');
        }
    }

    /**
     * Display a single transaction.
     */
    public function show(Transaction $transaction): View
    {
        $this->authorize('view', $transaction);

        $transaction->load(['customer', 'user', 'approver', 'flags', 'refundTransaction', 'originalTransaction']);

        // Surface the confirmation gate to approvers: a PendingApproval deal
        // that requires manager confirmation but has no Confirmed record yet
        // cannot be approved and must be routed through the confirmation flow.
        $requiresManagerConfirmation = $transaction->status === TransactionStatus::PendingApproval
            && $this->confirmationService->requiresConfirmation($transaction)
            && ! $transaction->confirmations()
                ->where('status', TransactionConfirmationStatus::Confirmed->value)
                ->exists();

        $canReverse = $this->cancellationService->canReverse($transaction);

        return view('transactions.show', compact('transaction', 'requiresManagerConfirmation', 'canReverse'));
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
     * Tellers may request cancellation of their own transactions; managers,
     * compliance officers, and admins per the requestCancellation policy.
     * The transaction must be eligible for cancellation, within the window,
     * not already cancelled, and not reversed.
     */
    private function ensureCanShowCancel(Transaction $transaction, User $user): ?RedirectResponse
    {
        $this->authorize('requestCancellation', $transaction);

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
        $this->authorize('viewAny', Transaction::class);

        $user = auth()->user();

        return view('transactions.export', [
            'branches' => $user->role->canManageAllBranches()
                ? Branch::all()
                : Branch::whereKey($user->branch_id)->get(),
            'types' => TransactionType::cases(),
        ]);
    }

    /**
     * Export transactions as CSV.
     */
    public function export(ExportTransactionRequest $request): BinaryFileResponse
    {
        $this->authorize('viewAny', Transaction::class);

        $filePath = $this->transactionExportService->exportTransactions(
            $request->validated(),
            $request->user()
        );

        return response()->download(Storage::path($filePath));
    }
}
