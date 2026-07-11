<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Http\Requests\CloseTillRequest;
use App\Http\Requests\OpenTillRequest;
use App\Http\Requests\TillReconciliationRequest;
use App\Http\Requests\TillReportRequest;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Services\Accounting\CurrencyPositionService;
use App\Services\AuditService;
use App\Services\Branch\TillBalanceManager;
use App\Services\Branch\TillService;
use App\Services\System\MathService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class StockCashController extends Controller
{
    public function __construct(
        protected MathService $mathService,
        protected CurrencyPositionService $currencyPositionService,
        protected TillService $tillService,
        protected AuditService $auditService,
        protected TillBalanceManager $tillBalanceManager,
    ) {}

    /**
     * Display stock and cash management dashboard
     */
    public function index(): View
    {
        $this->requireManagerOrAdmin();
        // Get current positions
        $positions = $this->currencyPositionService->getVisiblePositionsForUser(auth()->user());
        $totalPnl = $this->currencyPositionService->getTotalPnl();

        // Get till information
        $openTills = TillBalance::whereDate('date', today())
            ->whereNull('closed_at')
            ->distinct()
            ->pluck('till_id')
            ->toArray();

        $closedTills = TillBalance::whereDate('date', today())
            ->whereNotNull('closed_at')
            ->distinct()
            ->pluck('till_id')
            ->toArray();

        // Get today's till balances
        $todayBalances = TillBalance::with(['currency', 'opener', 'closer'])
            ->whereDate('date', today())
            ->get();

        // Calculate summary stats using collection aggregates
        $totalVariance = $todayBalances->sum('variance');

        $stats = [
            'total_currencies' => Currency::where('is_active', true)->count(),
            'active_positions' => $positions->count(),
            'open_tills' => count($openTills),
            'closed_tills' => count($closedTills),
            'total_variance' => $totalVariance,
        ];

        // Available currencies for opening tills
        $currencies = Currency::where('is_active', true)->get();

        // Calculate MYR cash in hand from today's till balances
        // For open tills: use opening_balance. For closed tills: use closing_balance
        $myrQuery = TillBalance::whereDate('date', today())
            ->where('currency_code', 'MYR');

        // Scope by branch for non-admin users
        $user = auth()->user();
        if (! $user->role->canManageAllBranches()) {
            $myrQuery->where('branch_id', $user->branch_id);
        }

        $myrBalances = $myrQuery->get();
        $myrCashInHand = $myrBalances->sum(fn ($b) => $b->closing_balance ?? $b->opening_balance);

        return view('pages.stock-cash.index', compact(
            'positions',
            'totalPnl',
            'openTills',
            'closedTills',
            'todayBalances',
            'stats',
            'currencies',
            'myrCashInHand'
        ));
    }

    /**
     * Open a till
     */
    public function openTill(OpenTillRequest $request): RedirectResponse
    {
        $this->requireManagerOrAdmin();

        $validated = $request->validated();

        $till = Counter::where('code', $validated['till_id'])
            ->orWhere('id', $validated['till_id'])
            ->first();

        if (! $till) {
            return back()->with('error', 'Till not found.');
        }

        try {
            $tillBalance = $this->tillBalanceManager->openTill(
                $till,
                $validated['currency_code'],
                (string) $validated['opening_balance'],
                auth()->id(),
                $validated['notes'] ?? null
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Failed to open till', ['error' => $e->getMessage()]);

            return back()->with('error', 'Unable to open till. Please try again.');
        }

        // Log till opening
        $this->auditService->log(
            'till_opened',
            auth()->id(),
            'TillBalance',
            $tillBalance->id,
            [],
            [
                'till_id' => $validated['till_id'],
                'currency_code' => $validated['currency_code'],
                'opening_balance' => $validated['opening_balance'],
            ]
        );

        return back()->with('success', 'Till opened successfully.');
    }

    /**
     * Close a till
     */
    public function closeTill(CloseTillRequest $request): RedirectResponse
    {
        $this->requireManagerOrAdmin();

        $validated = $request->validated();

        $tillBalance = TillBalance::where('till_id', $validated['till_id'])
            ->where('currency_code', $validated['currency_code'])
            ->whereDate('date', today())
            ->first();

        if (! $tillBalance) {
            return back()->with('error', 'Till not found for today.');
        }

        try {
            $tillBalance = $this->tillBalanceManager->closeTill(
                $tillBalance,
                (string) $validated['closing_balance'],
                auth()->id(),
                $validated['notes'] ?? null
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Failed to close till', ['error' => $e->getMessage()]);

            return back()->with('error', 'Unable to close till. Please try again.');
        }

        // Log till closing
        $this->auditService->log(
            'till_closed',
            auth()->id(),
            'TillBalance',
            $tillBalance->id,
            [
                'opening_balance' => $tillBalance->opening_balance,
            ],
            [
                'closing_balance' => $tillBalance->closing_balance,
                'variance' => $tillBalance->variance,
            ]
        );

        return back()->with('success', 'Till closed successfully. Variance: '.number_format((float) $tillBalance->variance, 2));
    }

    /**
     * Show currency position details
     */
    public function showPosition(CurrencyPosition $position): View
    {
        $this->requireManagerOrAdmin();
        $position->load('currency');

        // Load recent transactions for this currency position
        $transactions = Transaction::where('currency_code', $position->currency_code)
            ->where('type', TransactionType::Buy)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return view('stock-cash.position', compact('position', 'transactions'));
    }

    /**
     * Get till report
     */
    public function tillReport(TillReportRequest $request): View|RedirectResponse
    {
        $this->requireManagerOrAdmin();
        $validated = $request->validated();

        $date = $validated['date'] ?? today()->toDateString();

        $balances = TillBalance::with(['currency', 'opener', 'closer'])
            ->where('till_id', $validated['till_id'])
            ->whereDate('date', $date)
            ->get();

        if ($balances->isEmpty()) {
            return back()->with('error', 'No data found for specified till and date.');
        }

        return view('stock-cash.till-report', compact('balances', 'date'));
    }

    /**
     * Generate till reconciliation report
     */
    public function reconciliationReport(TillReconciliationRequest $request): View|RedirectResponse
    {
        $this->requireManagerOrAdmin();

        $validated = $request->validated();

        $date = $validated['date'] ?? today()->toDateString();
        $tillId = $validated['till_id'];

        // Get till balance for this date and till
        $tillBalance = TillBalance::with(['currency', 'opener', 'closer'])
            ->where('till_id', $tillId)
            ->whereDate('date', $date)
            ->first();

        if (! $tillBalance) {
            return back()->with('error', 'No till data found for the specified date and till.');
        }

        // Get all transactions for this till on this date
        $transactions = Transaction::with(['customer', 'currency'])
            ->where('till_id', $tillId)
            ->whereDate('created_at', $date)
            ->orderBy('created_at', 'asc')
            ->get();

        // Generate summary and reconciliation using service
        $summary = [
            'opening_balance' => $tillBalance->opening_balance,
            'total_buy_count' => $transactions->where('type', TransactionType::Buy)->count(),
            'total_buy_amount' => $this->tillService->calculateTransactionSum($transactions, TransactionType::Buy),
            'total_sell_count' => $transactions->where('type', TransactionType::Sell)->count(),
            'total_sell_amount' => $this->tillService->calculateTransactionSum($transactions, TransactionType::Sell),
            'total_transactions' => $transactions->count(),
            'net_flow' => $this->mathService->subtract(
                $this->tillService->calculateTransactionSum($transactions, TransactionType::Buy),
                $this->tillService->calculateTransactionSum($transactions, TransactionType::Sell)
            ),
        ];

        // Generate reconciliation data using service
        $reconciliation = $this->tillService->generateReconciliation($tillBalance, $transactions);

        return view('stock-cash.reconciliation', compact(
            'tillBalance',
            'date',
            'tillId',
            'transactions',
            'summary',
            'reconciliation'
        ));
    }
}
