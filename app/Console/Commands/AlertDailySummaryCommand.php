<?php

namespace App\Console\Commands;

use App\Services\AlertService;
use Illuminate\Console\Command;

class AlertDailySummaryCommand extends Command
{
    protected $signature = 'alert:daily-summary';

    protected $description = 'Send daily system health summary';

    protected AlertService $alertService;

    public function __construct(AlertService $alertService)
    {
        parent::__construct();
        $this->alertService = $alertService;
    }

    public function handle(): int
    {
        $this->info('Sending daily system health summary...');

        $alert = $this->alertService->sendDailySummary();

        if ($alert) {
            $this->info("Daily summary sent successfully. Alert ID: {$alert->id}");

            return 0;
        }

        $this->error('Failed to send daily summary');

        return 1;
    }
}
