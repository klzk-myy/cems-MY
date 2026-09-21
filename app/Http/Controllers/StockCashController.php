<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\TransactionType;
use App\Exceptions\Domain\DomainException;
use App\Http\Concerns\BranchScopedQuery;
use App\Http\Concerns\HandlesControllerErrors;
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
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StockCashController extends Controller
{
    use BranchScopedQuery, HandlesControllerErrors;

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
        $this->requirePermission(Permission::ManageStock);

        $user = auth()->user();

        // Get current positions
        $allPositions = $this->currencyPositionService->getVisiblePositionsForUser($user);
        $positions = $allPositions->paginate(25);
        $totalPnl = $this->currencyPositionService->getTotalPnl();

        // Get till information (scoped to the user's branch - admins see all)
        $openTills = $this->scopeByBranch(TillBalance::whereDate('date', today()->toDateString())->whereNull('closed_at'))
            ->distinct()->pluck('till_id')->toArray();

        $closedTills = $this->scopeByBranch(TillBalance::whereDate('date', today()->toDateString())->whereNotNull('closed_at'))
            ->distinct()->pluck('till_id')->toArray();

        // Get today's till balances, scoped to the user's branch (admins see all)
        $todayBalances = $this->scopeByBranch(TillBalance::with(['currency', 'opener', 'closer'])->whereDate('date', today()))
            ->get();

        // Calculate summary stats using bcmath (money must never go through float sums)
        $totalVariance = $todayBalances->reduce(
            fn (string $carry, TillBalance $balance) => $this->mathService->add($carry, (string) ($balance->variance ?? '0')),
            '0'
        );

        $stats = [
            'total_currencies' => Currency::where('is_active', true)->count(),
            'active_positions' => $allPositions->count(),
            'open_tills' => count($openTills),
            'closed_tills' => count($closedTills),
            'total_variance' => $totalVariance,
        ];

        // Available currencies for opening tills
        $currencies = Currency::where('is_active', true)->get();

        // Calculate MYR cash in hand from today's till balances
        // For open tills: use opening_balance. For closed tills: use closing_balance
        $myrQuery = $this->scopeByBranch(TillBalance::whereDate('date', today()->toDateString())->where('currency_code', Currency::baseCurrency()));

        $myrBalances = $myrQuery->get();
        $myrCashInHand = $myrBalances->reduce(
            fn (string $carry, TillBalance $b) => $this->mathService->add($carry, (string) ($b->closing_balance ?? $b->opening_balance ?? '0')),
            '0'
        );

        return view('stock-cash.index', compact(
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
        $this->requirePermission(Permission::ManageStock);

        $validated = $request->validated();

        $tillQuery = $this->scopeByBranch(Counter::where('code', $validated['till_id']));

        $till = $tillQuery->first();

        if (! $till) {
            return back()->with('error', 'Till not found.');
        }

        try {
            $tillBalance = $this->tillBalanceManager->openTill(
                $till,
                $validated['currency_code'],
                (string) $validated['opening_balance'],
                (int) auth()->id(),
                $validated['notes'] ?? null
            );
        } catch (ValidationException|DomainException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            return back()->with('error', 'Till operation failed. Please try again.');
        } catch (\Throwable $e) {
            return $this->handleExceptionWeb($e, 'Failed to open till', 'Unable to open till. Please try again.');
        }

        // Log till opening
        $this->auditService->log(
            'till_opened',
            (int) auth()->id(),
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
        $this->requirePermission(Permission::ManageStock);

        $validated = $request->validated();

        $tillQuery = $this->scopeByBranch(
            TillBalance::where('till_id', $validated['till_id'])
                ->where('currency_code', $validated['currency_code'])
                ->whereDate('date', today())
        );

        $tillBalance = $tillQuery->first();

        if (! $tillBalance) {
            return back()->with('error', 'Till not found for today.');
        }

        try {
            $tillBalance = $this->tillBalanceManager->closeTill(
                $tillBalance,
                (string) $validated['closing_balance'],
                (int) auth()->id(),
                $validated['difference_notes'] ?? null
            );
        } catch (ValidationException|DomainException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            return back()->with('error', 'Till operation failed. Please try again.');
        } catch (\Throwable $e) {
            return $this->handleExceptionWeb($e, 'Failed to close till', 'Unable to close till. Please try again.');
        }

        // Log till closing
        $this->auditService->log(
            'till_closed',
            (int) auth()->id(),
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
        $this->requirePermission(Permission::ManageStock);

        if (! $this->belongsToCurrentUserBranch($position)) {
            abort(403, 'You do not have access to this currency position.');
        }

        $position->load('currency');

        // Load recent transactions for this currency position — scoped to the
        // position's branch so a branch user does not see other branches' trades.
        $transactions = Transaction::with(['customer', 'currency'])
            ->where('currency_code', $position->currency_code)
            ->where('branch_id', $position->branch_id)
            ->where('type', TransactionType::Buy->value)
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return view('stock-cash.position', compact('position', 'transactions'));
    }

    /**
     * Get till report
     */
    public function tillReport(TillReportRequest $request): View|RedirectResponse
    {
        $this->requirePermission(Permission::ManageStock);
        $validated = $request->validated();

        $date = $validated['date'] ?? today()->toDateString();

        $balances = $this->scopeByBranch(
            TillBalance::with(['currency', 'opener', 'closer', 'counter'])
                ->where('till_id', $validated['till_id'])
                ->whereDate('date', $date)
        )->paginate(25);

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
        $this->requirePermission(Permission::ManageStock);

        $validated = $request->validated();

        $date = $validated['date'] ?? today()->toDateString();
        $tillId = $validated['till_id'];

        // Get all till balance rows (one per currency) for this date and till
        $tillBalances = $this->scopeByBranch(
            TillBalance::with(['currency', 'opener', 'closer'])
                ->where('till_id', $tillId)
                ->whereDate('date', $date)
        )->get();

        if ($tillBalances->isEmpty()) {
            return back()->with('error', 'No till data found for the specified date and till.');
        }

        $tillBalance = $tillBalances->first();

        // Get all transactions for this till on this date
        $allTransactions = Transaction::with(['customer', 'currency'])
            ->where('till_id', $tillId)
            ->forDateRange($date, $date)
            ->orderBy('created_at', 'asc')
            ->get();

        // Generate summary and reconciliation using service
        $buyTransactions = $allTransactions->where('type', TransactionType::Buy->value);
        $sellTransactions = $allTransactions->where('type', TransactionType::Sell->value);

        $summary = [
            'opening_balance' => $tillBalance->opening_balance,
            'total_buy_count' => $buyTransactions->count(),
            'total_buy_amount' => $this->tillService->calculateTransactionSum($allTransactions, TransactionType::Buy),
            // Foreign-currency totals: calculateTransactionSum() sums amount_myr
            // (MYR), so these are computed separately for the FCY summary rows.
            'total_buy_foreign' => $buyTransactions->reduce(
                fn (string $carry, Transaction $transaction) => $this->mathService->add($carry, (string) $transaction->quantity),
                '0'
            ),
            'total_sell_count' => $sellTransactions->count(),
            'total_sell_amount' => $this->tillService->calculateTransactionSum($allTransactions, TransactionType::Sell),
            'total_sell_foreign' => $sellTransactions->reduce(
                fn (string $carry, Transaction $transaction) => $this->mathService->add($carry, (string) $transaction->quantity),
                '0'
            ),
            'total_transactions' => $allTransactions->count(),
            'net_flow' => $this->mathService->subtract(
                $this->tillService->calculateTransactionSum($allTransactions, TransactionType::Buy),
                $this->tillService->calculateTransactionSum($allTransactions, TransactionType::Sell)
            ),
        ];

        // Generate reconciliation data using service
        $reconciliation = $this->tillService->generateReconciliation($tillBalances);

        $transactions = $allTransactions->paginate(50);

        return view('stock-cash.reconciliation', compact(
            'tillBalances',
            'date',
            'tillId',
            'transactions',
            'summary',
            'reconciliation'
        ));
    }
}
