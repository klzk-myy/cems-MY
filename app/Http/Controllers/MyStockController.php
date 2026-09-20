<?php

namespace App\Http\Controllers;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\ExchangeRate;
use App\Models\TellerAllocation;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class MyStockController extends Controller
{
    /**
     * Show the teller's stock and cash position for a business date.
     *
     * Per currency the sheet reports the day's allocated float (Opening),
     * the foreign-currency bought in and sold out with the MYR counterpart
     * legs (Buy / RM Cr / Sell / RM Dr), and the resulting Current holding.
     * Reversed transactions and refund records are excluded — the reversal
     * already compensated their legs, so counting them would double-count
     * the movement.
     */
    public function index(Request $request): View
    {
        $date = $this->resolveDate($request);
        $user = $request->user();

        $currencyRows = $this->buildCurrencyRows($user->id, $date);
        $myrRow = $this->buildMyrRow($user->id, $date);
        $rows = collect($currencyRows)->paginate(25);

        return view('my-stock.index', [
            'date' => $date,
            'rows' => $rows,
            'myrRow' => $myrRow,
            'valuation' => $this->valueHoldings($currencyRows, $myrRow['current'], $user->branch_id),
        ]);
    }

    /**
     * MYR-equivalent value of the teller's holdings: MYR cash plus each
     * foreign currency's Current quantity converted at the latest board
     * sell rate (the same convention the analytics reports use). A branch
     * rate card wins over the company card on a tie. Currencies with no
     * active rate are reported in 'unvalued' rather than silently dropped
     * from the total.
     *
     * @param  list<array{currency_code: string, opening: float, buy_quantity: float, buy_myr: float, sell_quantity: float, sell_myr: float, current: float}>  $currencyRows
     * @return array{cash_myr: float, stock_myr: float, total_myr: float, unvalued: list<string>}
     */
    private function valueHoldings(array $currencyRows, float $cashMyr, ?int $branchId): array
    {
        $codes = collect($currencyRows)->pluck('currency_code')->all();

        if ($codes === []) {
            return ['cash_myr' => $cashMyr, 'stock_myr' => 0.0, 'total_myr' => $cashMyr, 'unvalued' => []];
        }

        $rates = ExchangeRate::query()
            ->whereIn('currency_code', $codes)
            ->active()
            ->forBranchOrCompany($branchId ?? 0)
            ->get()
            ->groupBy('currency_code')
            ->map(fn ($cards) => $cards->sortByDesc(fn (ExchangeRate $r) => [
                $r->branch_id === $branchId ? 1 : 0,
                $r->fetched_at?->getTimestamp() ?? 0,
                $r->id,
            ])->first());

        $stock = 0.0;
        $unvalued = [];

        foreach ($currencyRows as $row) {
            $rate = $rates->get($row['currency_code']);

            if (! $rate) {
                $unvalued[] = $row['currency_code'];

                continue;
            }

            $stock += $row['current'] * (float) $rate->perUnitRate((string) $rate->rate_sell);
        }

        return [
            'cash_myr' => $cashMyr,
            'stock_myr' => $stock,
            'total_myr' => $cashMyr + $stock,
            'unvalued' => $unvalued,
        ];
    }

    private function resolveDate(Request $request): Carbon
    {
        $input = $request->query('date');

        if (is_string($input) && $input !== '') {
            try {
                return Carbon::parse($input)->startOfDay();
            } catch (\Throwable) {
                // Fall through to today on unparseable input.
            }
        }

        return now()->startOfDay();
    }

    /**
     * @return list<array{currency_code: string, opening: string, buy_quantity: string, buy_myr: string, sell_quantity: string, sell_myr: string, current: string}>
     */
    private function buildCurrencyRows(int $userId, Carbon $date): array
    {
        $openings = TellerAllocation::query()
            ->where('user_id', $userId)
            ->whereDate('session_date', $date)
            ->where('currency_code', '!=', 'MYR')
            ->get()
            ->groupBy('currency_code')
            ->map(fn ($allocations) => $allocations->sum(fn ($a) => (float) $a->allocated_quantity));

        $movements = $this->dailyMovements($userId, $date);

        $currencyCodes = $openings->keys()->merge($movements->keys())->unique()->sort()->values();

        return $currencyCodes
            ->map(function (string $code) use ($openings, $movements) {
                $movement = $movements->get($code) ?? ['buy_quantity' => 0.0, 'buy_myr' => 0.0, 'sell_quantity' => 0.0, 'sell_myr' => 0.0];
                $opening = (float) ($openings->get($code) ?? 0);

                return [
                    'currency_code' => $code,
                    'opening' => $opening,
                    'buy_quantity' => $movement['buy_quantity'],
                    'buy_myr' => $movement['buy_myr'],
                    'sell_quantity' => $movement['sell_quantity'],
                    'sell_myr' => $movement['sell_myr'],
                    'current' => $opening + $movement['buy_quantity'] - $movement['sell_quantity'],
                ];
            })
            ->all();
    }

    /**
     * MYR cash row: opening float plus MYR paid out on Buys (RM Cr) and
     * received on Sells (RM Dr). The FCY quantity columns do not apply.
     *
     * @return array{opening: float, buy_myr: float, sell_myr: float, current: float}
     */
    private function buildMyrRow(int $userId, Carbon $date): array
    {
        $opening = (float) TellerAllocation::query()
            ->where('user_id', $userId)
            ->whereDate('session_date', $date)
            ->where('currency_code', 'MYR')
            ->get()
            ->sum(fn ($a) => (float) $a->allocated_quantity);

        $myr = $this->dailyMovements($userId, $date)
            ->reduce(fn (array $carry, array $m) => [
                'buy_myr' => $carry['buy_myr'] + $m['buy_myr'],
                'sell_myr' => $carry['sell_myr'] + $m['sell_myr'],
            ], ['buy_myr' => 0.0, 'sell_myr' => 0.0]);

        return [
            'opening' => $opening,
            'buy_myr' => $myr['buy_myr'],
            'sell_myr' => $myr['sell_myr'],
            'current' => $opening - $myr['buy_myr'] + $myr['sell_myr'],
        ];
    }

    /**
     * Sum the day's settled transaction legs per currency.
     *
     * @return Collection<string, array{buy_quantity: float, buy_myr: float, sell_quantity: float, sell_myr: float}>
     */
    private function dailyMovements(int $userId, Carbon $date): Collection
    {
        return Transaction::query()
            ->selectRaw('currency_code, type, SUM(quantity) AS quantity_sum, SUM(amount_myr) AS myr_sum')
            ->where('user_id', $userId)
            ->whereDate('created_at', $date)
            ->whereIn('status', [TransactionStatus::Completed->value, TransactionStatus::Finalized->value])
            ->where('is_refund', false)
            ->groupBy('currency_code', 'type')
            ->get()
            ->groupBy('currency_code')
            ->map(function ($legs) {
                $buy = $legs->firstWhere('type', TransactionType::Buy->value);
                $sell = $legs->firstWhere('type', TransactionType::Sell->value);

                return [
                    'buy_quantity' => (float) ($buy->quantity_sum ?? 0),
                    'buy_myr' => (float) ($buy->myr_sum ?? 0),
                    'sell_quantity' => (float) ($sell->quantity_sum ?? 0),
                    'sell_myr' => (float) ($sell->myr_sum ?? 0),
                ];
            });
    }
}
