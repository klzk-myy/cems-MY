<?php

namespace App\Http\Controllers;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Services\AccountingService;
use App\Services\AuditService;
use App\Services\ComplianceService;
use App\Services\CurrencyPositionService;
use App\Services\MathService;
use App\Services\TransactionMonitoringService;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
        protected AuditService $auditService
    ) {}

    /**
     * Display list of transactions
     */
    public function index()
    {
        $query = Transaction::with(['customer', 'user']);

        // Branch segregation: non-admin users can only see their branch's transactions
        $user = auth()->user();
        if ($user && $user->branch_id !== null) {
            $query->where('branch_id', $user->branch_id);
        }

        $transactions = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('transactions.index', compact('transactions'));
    }

    /**
     * Show form to create new transaction
     */
    public function create()
    {
        $currencies = Currency::where('is_active', true)->get()->pluck('name', 'code');
        $customers = Customer::all();
        $branches = Branch::all();
        $counters = Counter::where('status', 'active')->get();

        // Get suggested rate for default currency
        $suggested_rate = null;

        // Branch segregation: non-admin users can only see tills at their branch
        $tillQuery = TillBalance::where('date', today())
            ->whereNull('closed_at')
            ->with('currency');

        $user = auth()->user();
        if ($user && $user->branch_id !== null) {
            $tillQuery->where('branch_id', $user->branch_id);
        }
        $tillBalances = $tillQuery->get();

        return view('transactions.create', compact('currencies', 'customers', 'tillBalances', 'branches', 'counters', 'suggested_rate'));
    }

    /**
     * Store new transaction
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'type' => ['required', 'in:'.TransactionType::Buy->value.','.TransactionType::Sell->value],
            'currency_code' => 'required|exists:currencies,code',
            'amount_foreign' => 'required|numeric|min:0.01|max:9999999999.9999',
            'rate' => 'required|numeric|min:0.0001|max:999999',
            'purpose' => 'required|string|max:255',
            'source_of_funds' => 'required|string|max:255',
            'branch_id' => 'required|exists:branches,id',
            'counter_id' => 'required|exists:counters,id',
            'idempotency_key' => 'required|string|max:100|unique:transactions,idempotency_key',
        ]);

        // Derive till_id from counter (backward compatibility)
        $validated['till_id'] = (string) $validated['counter_id'];

        // Note: XSS protection is handled by Blade's automatic escaping on output

        try {
            $transaction = $this->transactionService->createTransaction(
                $validated,
                auth()->id(),
                $request->ip()
            );

            // Audit logging for successful transaction creation
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

        } catch (\InvalidArgumentException $e) {
            // These are expected validation/business rule exceptions (like duplicate, insufficient stock)
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

            // Return generic message to user to avoid information disclosure
            return back()->with('error', 'Transaction failed. Please contact support if the problem persists.')->withInput();
        }
    }

    /**
     * Display single transaction
     */
    public function show(Transaction $transaction)
    {
        $transaction->load(['customer', 'user', 'approver', 'flags']);

        return view('transactions.show', compact('transaction'));
    }

    /**
     * Generate PDF receipt
     */
    public function receipt(Transaction $transaction)
    {
        if (! $transaction->status->isCompleted()) {
            return back()->with('error', 'Receipts can only be generated for completed transactions.');
        }

        $transaction->load(['customer', 'user', 'approver']);

        // Generate barcode (Code128) for transaction reference number
        $barcodeImage = null;
        $barcodeText = str_pad($transaction->id, 10, '0', STR_PAD_LEFT);
        try {
            $barcodeGenerator = new BarcodeGeneratorPNG;
            $barcodeData = $barcodeGenerator->getBarcode($barcodeText, $barcodeGenerator::TYPE_CODE_128);
            $barcodeImage = 'data:image/png;base64,'.base64_encode($barcodeData);
        } catch (\Exception $e) {
            // Graceful fallback if barcode generation fails
            $barcodeImage = null;
        }

        // Generate QR code with transaction verification data
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
            // Graceful fallback if QR code generation fails
            $qrCodeImage = null;
        }

        $pdf = app('dompdf.wrapper');
        $pdf->loadView('transactions.receipt', compact('transaction', 'barcodeImage', 'qrCodeImage', 'barcodeText'));
        $pdf->setPaper([0, 0, 226.77, 841.89], 'portrait');
        $filename = 'receipt_'.str_pad($transaction->id, 8, '0', STR_PAD_LEFT).'.pdf';

        return $pdf->download($filename);
    }
}
