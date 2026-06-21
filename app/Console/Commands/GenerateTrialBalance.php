<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\HasReportFormatting;
use App\Services\Accounting\AccountingService;
use App\Services\System\MathService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateTrialBalance extends Command
{
    use HasReportFormatting;

    protected $signature = 'report:trial-balance {--date= : Specific date (Y-m-d), defaults to last closed period}';

    protected $description = 'Generate trial balance report for accounting period';

    public function handle(AccountingService $accountingService): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::yesterday();

        $this->info("Generating Trial Balance for {$date->toDateString()}...");

        try {
            $reportData = $accountingService->generateTrialBalance($date);

            $filename = $this->getReportFilename('TrialBalance', 'report');
            $filepath = $this->getReportPath($filename);

            $totalDebit = '0';
            $totalCredit = '0';
            $math = app(MathService::class);
            $csvContent = "Account Code,Account Name,Debit,Credit\n";

            foreach ($reportData as $row) {
                $csvContent .= implode(',', [
                    $row['account_code'],
                    $row['account_name'],
                    number_format((float) $row['debit'], 2),
                    number_format((float) $row['credit'], 2),
                ])."\n";
                $totalDebit = $math->add($totalDebit, $row['debit']);
                $totalCredit = $math->add($totalCredit, $row['credit']);
            }

            $csvContent .= implode(',', ['', 'TOTAL', number_format((float) $totalDebit, 2), number_format((float) $totalCredit, 2)])."\n";

            $this->saveReportCsv($filepath, $csvContent);

            $this->createReportRecord('TrialBalance', $date->startOfMonth(), $date->endOfMonth());

            $this->info("Trial Balance generated: {$filepath}");

            return 0;
        } catch (\Exception $e) {
            $this->error('Trial Balance generation failed: '.$e->getMessage());

            return 1;
        }
    }
}
