<?php

namespace App\Http\Controllers;

use App\Enums\TransactionStatus;
use App\Exceptions\Domain\AllocationValidationException;
use App\Exceptions\Domain\DuplicateTransactionException;
use App\Exceptions\Domain\InsufficientStockException;
use App\Exceptions\Domain\InvalidCurrencyException;
use App\Exceptions\Domain\PepApprovalRequiredException;
use App\Exceptions\Domain\TillBalanceMissingException;
use App\Http\Requests\IndexTransactionRequest;
use App\Http\Requests\StoreTransactionRequest;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\CurrencyPositionService;
use App\Services\AuditService;
use App\Services\Compliance\ComplianceService;
use App\Services\System\MathService;
use App\Services\Transaction\TransactionCancellationService;
use App\Services\Transaction\TransactionMonitoringService;
use App\Services\Transaction\TransactionService;
use Barryvdh\DomPDF\PDF;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Picqer\Barcode\BarcodeGeneratorPNG;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class TransactionController extends Controller
{
    public function __construct(
        protected CurrencyPositionService $positionService,
        protected ComplianceService $complianceService,
        protected TransactionMonitoringService $monitoringService,
        protected MathService $mathService,
        protected AccountingService $accountingService,
        protected TransactionService $transactionService,
        protected AuditService $auditService,
        protected TransactionCancellationService $cancellationService,
        private PDF $pdf,
        private BarcodeGeneratorPNG $barcodeGenerator
    ) {}

    /**
     * Display a paginated list of transactions.
     *
     * Non-admin users can only see transactions for their own branch.
     */
    public function index(IndexTransactionRequest $request): View
    {
        $validated = $request->validated();

        $query = Transaction::with(['customer', 'currency', 'user', 'branch'])
            ->when($validated['search'] ?? null, function ($q, string $search) {
                $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $search);

                return $q->whereRaw('reference like ? escape \'\\\'', ["%{$escaped}%"]);
            })
            ->when($validated['status'] ?? null, function ($q, string $status) {
                return $q->where('status', $status);
            })
            ->when($validated['customer_id'] ?? null, function ($q, int $customerId) {
                return $q->where('customer_id', $customerId);
            });

        $user = auth()->user();
        if ($user && $user->branch_id !== null) {
            $query->where('branch_id', $user->branch_id);
        }

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
        $currencies = Currency::select('code', 'name')->where('is_active', true)->get()->pluck('name', 'code');
        $customers = Customer::select('id', 'full_name')->orderBy('full_name')->get();
        $branches = Branch::select('id', 'name')->orderBy('name')->get();
        $counters = Counter::where('status', 'active')->get();

        $suggested_rate = null;

        $tillQuery = TillBalance::where('date', today())
            ->whereNull('closed_at')
            ->with('currency');

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

        $validated['till_id'] = (string) $validated['counter_id'];

        try {
            $transaction = $this->transactionService->createTransaction(
                $validated,
                auth()->id(),
                $request->ip()
            );

            $this->auditService->logTransaction('transaction_created', $transaction->id, [
                'new' => [
                    'type' => $transaction->type->value,
                    'currency_code' => $transaction->currency_code,
                    'amount_foreign' => $transaction->amount_foreign,
                    'amount_local' => $transaction->amount_local,
                    'rate' => $transaction->rate,
                    'customer_id' => $transaction->customer_id,
                    'purpose' => $transaction->purpose,
                    'source_of_funds' => $transaction->source_of_funds,
                    'status' => $transaction->status->value,
                ],
            ]);

            if ($transaction->status === TransactionStatus::PendingApproval) {
                return redirect()->route('transactions.show', $transaction)
                    ->with('warning', 'Transaction created and pending manager approval.');
            }

            return redirect()->route('transactions.show', $transaction)
                ->with('success', 'Transaction completed successfully. Receipt #'.$transaction->id);

        } catch (InvalidCurrencyException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        } catch (InsufficientStockException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        } catch (DuplicateTransactionException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        } catch (AllocationValidationException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        } catch (TillBalanceMissingException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        } catch (PepApprovalRequiredException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        } catch (\Exception $e) {
            Log::error('Transaction creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
            ]);

            $this->auditService->logWithSeverity(
                'transaction_failed',
                [
                    'user_id' => auth()->id(),
                    'description' => $e->getMessage(),
                ],
                'ERROR'
            );

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

        return view('transactions.show', compact('transaction'));
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
        if ($response = $this->ensureCanGenerateReceipt($transaction)) {
            return $response;
        }

        $transaction->load(['customer', 'user', 'approver']);

        $barcodeImage = null;
        $barcodeText = str_pad($transaction->id, 10, '0', STR_PAD_LEFT);
        try {
            $barcodeData = $this->barcodeGenerator->getBarcode($barcodeText, $this->barcodeGenerator::TYPE_CODE_128);
            $barcodeImage = 'data:image/png;base64,'.base64_encode($barcodeData);
        } catch (\Exception $e) {
            $barcodeImage = null;
        }

        $qrCodeImage = null;
        try {
            $qrCodeData = QrCode::format('png')
                ->size(150)
                ->generate(json_encode([
                    'id' => $transaction->id,
                    'amount' => $transaction->amount_local,
                    'currency' => $transaction->currency_code,
                    'date' => $transaction->created_at->toIso8601String(),
                    'customer_id' => $transaction->customer_id,
                    'type' => $transaction->type->value,
                    'verify' => url('/verify/transaction/'.$transaction->id),
                ]));
            $qrCodeImage = 'data:image/png;base64,'.base64_encode($qrCodeData);
        } catch (\Exception $e) {
            $qrCodeImage = null;
        }

        $pdf = $this->pdf;
        $pdf->loadView('transactions.receipt', compact('transaction', 'barcodeImage', 'qrCodeImage', 'barcodeText'));
        $pdf->setPaper([0, 0, 226.77, 841.89], 'portrait');
        $filename = 'receipt_'.str_pad($transaction->id, 8, '0', STR_PAD_LEFT).'.pdf';

        return $pdf->download($filename);
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
        if (! $user->role->isManager()) {
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
}
