<?php

namespace App\Console\Commands;

use App\Models\ReportGenerated;
use App\Services\AccountingService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateTrialBalance extends Command
{
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

            $filename = 'TrialBalance_'.$date->format('Y-m-d').'.csv';
            $filepath = storage_path('app/reports/'.$filename);

            if (! is_dir(dirname($filepath))) {
                mkdir(dirname($filepath), 0755, true);
            }

            $handle = fopen($filepath, 'w');
            fputcsv($handle, ['Account Code', 'Account Name', 'Debit', 'Credit']);

            $totalDebit = '0';
            $totalCredit = '0';

            foreach ($reportData as $row) {
                fputcsv($handle, [
                    $row['account_code'],
                    $row['account_name'],
                    number_format((float) $row['debit'], 2),
                    number_format((float) $row['credit'], 2),
                ]);
                $totalDebit = bcadd($totalDebit, $row['debit'], 2);
                $totalCredit = bcadd($totalCredit, $row['credit'], 2);
            }

            // Totals row
            fputcsv($handle, ['', 'TOTAL', number_format((float) $totalDebit, 2), number_format((float) $totalCredit, 2)]);
            fclose($handle);

            ReportGenerated::create([
                'report_type' => 'TrialBalance',
                'period_start' => $date->startOfMonth(),
                'period_end' => $date->endOfMonth(),
                'generated_by' => 1,
                'generated_at' => now(),
                'file_format' => 'CSV',
                'status' => 'Generated',
            ]);

            $this->info("Trial Balance generated: {$filepath}");

            return 0;
        } catch (\Exception $e) {
            $this->error('Trial Balance generation failed: '.$e->getMessage());

            return 1;
        }
    }
}
