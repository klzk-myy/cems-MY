<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Http\Requests\CloseTillRequest;
use App\Http\Requests\OpenTillRequest;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\SystemLog;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Services\CurrencyPositionService;
use App\Services\MathService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class StockCashController extends Controller
{
    protected MathService $mathService;

    protected CurrencyPositionService $currencyPositionService;

    public function __construct(MathService $mathService, CurrencyPositionService $currencyPositionService)
    {
        $this->mathService = $mathService;
        $this->currencyPositionService = $currencyPositionService;
    }

    /**
     * Display stock and cash management dashboard
     */
    public function index()
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

        // Calculate summary stats using MathService for monetary values
        $totalVariance = '0';
        foreach ($todayBalances as $balance) {
            $totalVariance = $this->mathService->add($totalVariance, (string) ($balance->variance ?? 0));
        }

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
        $myrCashInHand = '0';
        foreach ($myrBalances as $balance) {
            // Use closing_balance if closed, otherwise opening_balance
            $balanceAmount = $balance->closed_at
                ? ($balance->closing_balance ?? '0')
                : ($balance->opening_balance ?? '0');
            $myrCashInHand = $this->mathService->add($myrCashInHand, (string) $balanceAmount);
        }

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
    public function openTill(OpenTillRequest $request)
    {
        $this->requireManagerOrAdmin();

        $validated = $request->validated();

        // Check if already open
        $existing = TillBalance::where('till_id', $validated['till_id'])
            ->where('currency_code', $validated['currency_code'])
            ->whereDate('date', today())
            ->first();

        if ($existing) {
            return back()->with('error', 'Till already opened for this currency today.');
        }

        $tillBalance = TillBalance::create([
            'till_id' => $validated['till_id'],
            'currency_code' => $validated['currency_code'],
            'opening_balance' => $validated['opening_balance'],
            'date' => today(),
            'opened_by' => auth()->id(),
            'notes' => $validated['notes'] ?? null,
        ]);

        // Log till opening
        SystemLog::create([
            'user_id' => auth()->id(),
            'action' => 'till_opened',
            'entity_type' => 'TillBalance',
            'entity_id' => $tillBalance->id,
            'new_values' => [
                'till_id' => $validated['till_id'],
                'currency_code' => $validated['currency_code'],
                'opening_balance' => $validated['opening_balance'],
            ],
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Till opened successfully.');
    }

    /**
     * Close a till
     */
    public function closeTill(CloseTillRequest $request)
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

        if ($tillBalance->closed_at) {
            return back()->with('error', 'Till already closed for today.');
        }

        // Calculate expected closing balance based on transactions
        $netFlow = Transaction::where('till_id', $validated['till_id'])
            ->where('currency_code', $validated['currency_code'])
            ->whereDate('created_at', today())
            ->selectRaw("SUM(CASE WHEN type='Buy' THEN amount_local ELSE -amount_local END) as net")
            ->value('net') ?? 0;

        $expectedClosing = $this->mathService->add(
            (string) $tillBalance->opening_balance,
            (string) $netFlow
        );
        $variance = $this->mathService->subtract(
            (string) $validated['closing_balance'],
            $expectedClosing
        );

        $tillBalance->update([
            'closing_balance' => $validated['closing_balance'],
            'variance' => $variance,
            'closed_by' => auth()->id(),
            'closed_at' => now(),
            'notes' => $validated['notes'] ?? null,
        ]);

        // Log till closing
        SystemLog::create([
            'user_id' => auth()->id(),
            'action' => 'till_closed',
            'entity_type' => 'TillBalance',
            'entity_id' => $tillBalance->id,
            'old_values' => [
                'opening_balance' => $tillBalance->opening_balance,
            ],
            'new_values' => [
                'closing_balance' => $validated['closing_balance'],
                'variance' => $variance,
            ],
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Till closed successfully. Variance: '.number_format((float) $variance, 2));
    }

    /**
     * Calculate sum of transaction amounts using MathService for precision.
     *
     * @param  Collection  $transactions
     */
    protected function calculateTransactionSum($transactions, TransactionType $type): string
    {
        $sum = '0';
        foreach ($transactions->where('type', $type) as $transaction) {
            $sum = $this->mathService->add($sum, (string) $transaction->amount_local);
        }

        return $sum;
    }

    /**
     * Show currency position details
     */
    public function showPosition(CurrencyPosition $position)
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
    public function tillReport(Request $request)
    {
        $this->requireManagerOrAdmin();
        $validated = $request->validate([
            'till_id' => 'required|string',
            'date' => 'nullable|date',
        ]);

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
    public function reconciliationReport(Request $request)
    {
        $this->requireManagerOrAdmin();

        $validated = $request->validate([
            'date' => 'nullable|date',
            'till_id' => 'required|string',
        ]);

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

        // Calculate summary statistics using MathService for precision
        $buyAmount = $this->calculateTransactionSum($transactions, TransactionType::Buy);
        $sellAmount = $this->calculateTransactionSum($transactions, TransactionType::Sell);
        $netFlow = $this->mathService->subtract($buyAmount, $sellAmount);

        $summary = [
            'opening_balance' => $tillBalance->opening_balance,
            'total_buy_count' => $transactions->where('type', TransactionType::Buy)->count(),
            'total_buy_amount' => $buyAmount,
            'total_sell_count' => $transactions->where('type', TransactionType::Sell)->count(),
            'total_sell_amount' => $sellAmount,
            'total_transactions' => $transactions->count(),
            'net_flow' => $netFlow,
        ];

        // Calculate expected closing balance
        // For buy: + foreign currency (stock in), - MYR (cash out)
        // For sell: - foreign currency (stock out), + MYR (cash in)
        $expectedClosing = $this->mathService->add(
            (string) $tillBalance->opening_balance,
            (string) $summary['net_flow']
        );

        // Get actual closing balance (if till is closed) - keep as string for precision
        $actualClosing = $tillBalance->closing_balance
            ? (string) $tillBalance->closing_balance
            : null;

        // Calculate variance
        $variance = $actualClosing !== null
            ? $this->mathService->subtract((string) $actualClosing, (string) $expectedClosing)
            : null;

        $reconciliation = [
            'opening_balance' => $summary['opening_balance'],
            'purchases' => [
                'count' => $summary['total_buy_count'],
                'total' => $summary['total_buy_amount'],
            ],
            'sales' => [
                'count' => $summary['total_sell_count'],
                'total' => $summary['total_sell_amount'],
            ],
            'expected_closing' => $expectedClosing,
            'actual_closing' => $actualClosing,
            'variance' => $variance,
            'is_closed' => $tillBalance->closed_at !== null,
        ];

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
