<?php

namespace App\Services\Reporting\Generators;

use App\Exceptions\Domain\ReportValidationException;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\User;
use App\Services\Reporting\CsvReportWriter;
use App\Services\Reporting\TransactionReportQuery;
use App\Services\System\MathService;
use Carbon\Carbon;

class LmcaReportGenerator
{
    public function __construct(
        protected MathService $mathService,
        protected TransactionReportQuery $transactionReportQuery,
        protected CsvReportWriter $csvReportWriter,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function generate(string $month): array
    {
        $startDate = Carbon::parse($month)->startOfMonth();
        $endDate = Carbon::parse($month)->endOfMonth();

        $currencies = Currency::where('is_active', true)->get();
        $currencyCodes = $currencies->pluck('code')->toArray();
        $currencyData = [];

        $transactionQuery = $this->transactionReportQuery;

        $allTxns = $transactionQuery
            ->completed()
            ->forDateRange($startDate->toDateString(), $endDate->toDateString())
            ->whereIn('currency_code', $currencyCodes)
            ->get()
            ->groupBy('currency_code');

        $positions = CurrencyPosition::whereIn('currency_code', $currencyCodes)
            ->get()
            ->keyBy('currency_code');

        foreach ($currencies as $currency) {
            $currencyTxns = $allTxns->get($currency->code, collect());
            $myrVolumes = $transactionQuery->buySellVolumes($currencyTxns);
            $foreignVolumes = $transactionQuery->buySellVolumes($currencyTxns, 'amount_foreign');

            $openingPosition = $positions->get($currency->code);

            // No historical position snapshots exist, so the opening stock is
            // reconstructed backwards from today's live position. Sign
            // convention mirrors CurrencyPositionService::updatePosition:
            // a Buy adds amount_foreign to the position, a Sell subtracts it,
            // hence closing = opening + buys - sells.
            $closingStock = $openingPosition ? (string) $openingPosition->quantity : '0';
            $netMovement = $this->mathService->subtract(
                (string) $foreignVolumes['buy_volume'],
                (string) $foreignVolumes['sell_volume']
            );
            $openingStock = $this->mathService->subtract($closingStock, $netMovement);

            $currencyData[] = [
                'currency_code' => $currency->code,
                'currency_name' => $currency->name,
                'buy_count' => $myrVolumes['buy_count'],
                'buy_volume' => $foreignVolumes['buy_volume'],
                'buy_value_myr' => $myrVolumes['buy_volume'],
                'sell_count' => $myrVolumes['sell_count'],
                'sell_volume' => $foreignVolumes['sell_volume'],
                'sell_value_myr' => $myrVolumes['sell_volume'],
                'opening_stock' => $openingStock,
                'closing_stock' => $closingStock,
            ];
        }

        $customerCount = $this->transactionReportQuery
            ->completed()
            ->forDateRange($startDate->toDateString(), $endDate->toDateString())
            ->distinct('customer_id')
            ->count('customer_id');

        $staffCount = User::query()
            ->where('is_active', true)
            ->count();

        $licenseNumber = config('cems.license_number');
        if (empty($licenseNumber)) {
            throw new ReportValidationException('BNM license number is not configured. Set BNM_LICENSE_NUMBER in .env.');
        }

        return [
            'license_number' => $licenseNumber,
            'reporting_period' => $month,
            'report_date' => now()->format('Y-m-d'),
            'currencies' => $currencyData,
            'customer_count' => $customerCount,
            'staff_count' => $staffCount,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    public function generateCsv(string $month): string
    {
        $data = $this->generate($month);
        $filename = "LMCA_{$month}.csv";

        $titleRows = [
            ['BNM Form LMCA - Monthly Report'],
            ['License Number', $data['license_number']],
            ['Reporting Period', $data['reporting_period']],
            ['Report Date', $data['report_date']],
        ];

        $headers = [
            'Currency',
            'Buy Count',
            'Buy Volume (Foreign)',
            'Buy Value (MYR)',
            'Sell Count',
            'Sell Volume (Foreign)',
            'Sell Value (MYR)',
            'Opening Stock',
            'Closing Stock',
        ];

        $rows = [];
        foreach ($data['currencies'] as $row) {
            $rows[] = [
                $row['currency_code'],
                $row['buy_count'],
                $row['buy_volume'],
                $row['buy_value_myr'],
                $row['sell_count'],
                $row['sell_volume'],
                $row['sell_value_myr'],
                $row['opening_stock'],
                $row['closing_stock'],
            ];
        }

        $rows[] = [];
        $rows[] = ['Total Customers Served', $data['customer_count']];
        $rows[] = ['Total Active Staff', $data['staff_count']];

        return $this->csvReportWriter->writeWithTitleRows($filename, $titleRows, $headers, $rows);
    }
}
