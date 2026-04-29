<?php

namespace App\Console;

use App\Jobs\Accounting\ReconcileDeferredAccountingJob;
use App\Jobs\Compliance\CounterfeitAlertJob;
use App\Jobs\Compliance\CurrencyFlowJob;
use App\Jobs\Compliance\CustomerLocationAnomalyJob;
use App\Jobs\Compliance\SanctionsRescreeningJob;
use App\Jobs\Compliance\StrDeadlineMonitorJob;
use App\Jobs\Compliance\StructuringMonitorJob;
use App\Jobs\Compliance\VelocityMonitorJob;
use App\Jobs\ImportSanctionsJob;
use App\Jobs\RescreenHighRiskCustomersJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // ============ DAILY REPORTS ============

        // MSB(2) - Daily transaction summary (previous day)
        $schedule->command('report:msb2')
            ->dailyAt('00:05')
            ->appendOutputTo(storage_path('logs/report-msb2.log'));

        // Position Limit - Daily limit utilization check
        $schedule->command('report:position-limit')
            ->dailyAt('06:00')
            ->appendOutputTo(storage_path('logs/report-position-limit.log'));

        // EOD Reconciliation - End of day reconciliation (runs after counters close)
        $schedule->command('report:eod')
            ->dailyAt('20:00')
            ->appendOutputTo(storage_path('logs/report-eod.log'));

        // Reconcile Deferred Accounting - Auto-create journal entries for Enhanced CDD transactions
        $schedule->job(fn () => app(ReconcileDeferredAccountingJob::class))
            ->dailyAt('21:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/reconcile-deferred-accounting.log'));

        // ============ WEEKLY REPORTS ============

        // Trial Balance - Every Sunday at 01:00
        $schedule->command('report:trial-balance')
            ->weekly()
            ->sundays()
            ->at('01:00')
            ->appendOutputTo(storage_path('logs/report-trial-balance.log'));

        // ============ MONTHLY REPORTS (Run on 1st of each month) ============

        // LMCA - BNM Monthly Form (for previous month) - 1st of month at 00:30
        $schedule->command('report:lmca')
            ->cron('30 0 1 * *')
            ->appendOutputTo(storage_path('logs/report-lmca.log'));

        // LCTR - Cash Transaction Report (for previous month) - 1st of month at 00:45
        $schedule->command('report:lctr')
            ->cron('45 0 1 * *')
            ->appendOutputTo(storage_path('logs/report-lctr.log'));

        // Sanctions Rescreening (BNM monthly requirement) - 1st of month at 03:00
        $schedule->command('compliance:rescreen --days=30')
            ->cron('0 3 1 * *')
            ->appendOutputTo(storage_path('logs/compliance-rescreen.log'));

        // Cleanup old temp reports - 1st of month at 02:00
        $schedule->command('reports:cleanup --days=90')
            ->cron('0 2 1 * *')
            ->appendOutputTo(storage_path('logs/reports-cleanup.log'));

        // ============ QUARTERLY REPORTS ============

        // Quarterly Large Value Report - Run on 1st of months 4, 7, 10, 1 (Apr=4, Jul=7, Oct=10, Jan=1)
        $schedule->command('report:qlvr')
            ->cron('0 1 1 1,4,7,10 *')
            ->appendOutputTo(storage_path('logs/report-qlvr.log'));

        // ============ ANNUAL REPORTS ============

        // Report Archival (BNM requires 7-year retention) - January 1st at 04:00
        $schedule->command('reports:archive --months=12')
            ->cron('0 4 1 1 *')
            ->appendOutputTo(storage_path('logs/reports-archive.log'));

        // Revaluation at end of month - last day at 23:59
        $schedule->command('revaluation:run')
            ->cron('59 23 L * *')
            ->appendOutputTo(storage_path('logs/revaluation.log'));

        // Month-End Close - 1st of month at 01:00
        $schedule->command('accounting:month-end')
            ->monthlyOn(1, '01:00')
            ->appendOutputTo(storage_path('logs/month-end-close.log'));

        // ============ COMPLIANCE MONITORS ============

        // Sanctions Rescreening Monitor - Weekly on Sunday at 02:00
        $schedule->job(new SanctionsRescreeningJob)
            ->weeklyOn(0, '02:00')
            ->appendOutputTo(storage_path('logs/monitor-sanctions-rescreen.log'));

        // Customer Location Anomaly Monitor - Daily at 03:00
        $schedule->job(new CustomerLocationAnomalyJob)
            ->dailyAt('03:00')
            ->appendOutputTo(storage_path('logs/monitor-location-anomaly.log'));

        // Currency Flow Monitor - Daily at 03:30
        $schedule->job(new CurrencyFlowJob)
            ->dailyAt('03:30')
            ->appendOutputTo(storage_path('logs/monitor-currency-flow.log'));

        // Counterfeit Alert Monitor - Daily at 04:00
        $schedule->job(new CounterfeitAlertJob)
            ->dailyAt('04:00')
            ->appendOutputTo(storage_path('logs/monitor-counterfeit-alert.log'));

        // Velocity Monitor - Daily at 04:30 (AML velocity detection)
        $schedule->job(new VelocityMonitorJob)
            ->dailyAt('04:30')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/monitor-velocity.log'));

        // Structuring Monitor - Daily at 04:45 (transaction aggregation detection)
        $schedule->job(new StructuringMonitorJob)
            ->dailyAt('04:45')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/monitor-structuring.log'));

        // STR Deadline Monitor - Daily at 05:00 (STR submission deadline tracking)
        $schedule->job(new StrDeadlineMonitorJob)
            ->dailyAt('05:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/monitor-str-deadline.log'));

        // ============ SYSTEM MONITORING ============

        // Health checks - Every 5 minutes
        $schedule->command('monitor:check --alert')
            ->everyFiveMinutes()
            ->appendOutputTo(storage_path('logs/monitor-health-check.log'));

        // Daily summary report - Every day at 08:00
        $schedule->command('alert:daily-summary')
            ->dailyAt('08:00')
            ->appendOutputTo(storage_path('logs/alert-daily-summary.log'));

        // Cleanup old alerts - Weekly on Sunday at 02:00
        $schedule->command('alert:cleanup --days=30')
            ->weeklyOn(0, '02:00')
            ->appendOutputTo(storage_path('logs/alert-cleanup.log'));

        // ============ SANCTIONS AUTO-UPDATES ============

        // Daily sanctions list update at 03:00 (BNM requires within 24 hours)
        $schedule->command('sanctions:update')
            ->dailyAt('03:00')
            ->appendOutputTo(storage_path('logs/sanctions-update.log'));

        // Check sanctions status and alert if failed
        $schedule->command('sanctions:status')
            ->dailyAt('08:00')
            ->appendOutputTo(storage_path('logs/sanctions-status-check.log'));

        // UN Consolidated sanctions list - Daily at 1 AM
        $schedule->job(new ImportSanctionsJob)
            ->dailyAt('01:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/sanctions-import-un.log'));

        // MOHA Malaysia sanctions list - Weekly on Sunday at 2 AM
        $schedule->job(new ImportSanctionsJob)
            ->weeklyOn(0, '02:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/sanctions-import-moha.log'));

        // High risk customer rescreening - Daily at 4 AM
        $schedule->job(new RescreenHighRiskCustomersJob)
            ->dailyAt('04:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/sanctions-rescreen-highrisk.log'));

        // ============ BACKUP & RECOVERY ============
        // https://github.com/spatie/laravel-backup

        // Daily database backup at 02:00
        $schedule->command('backup:run --type=database')
            ->dailyAt('02:00')
            ->appendOutputTo(storage_path('logs/backup-database.log'));

        // Customer Risk Review - Daily at 02:00 (after backup, before morning activity)
        $schedule->command('customer:risk-review')
            ->dailyAt('02:00')
            ->appendOutputTo(storage_path('logs/customer-risk-review.log'));

        // Weekly full backup (files + database) on Sunday at 03:00
        $schedule->command('backup:run --type=full')
            ->weeklyOn(0, '03:00')
            ->appendOutputTo(storage_path('logs/backup-full.log'));

        // Monthly archive to S3 Glacier on 1st at 04:00 (BNM 7-year retention)
        $schedule->command('backup:run --type=full --disk=s3')
            ->monthlyOn(1, '04:00')
            ->appendOutputTo(storage_path('logs/backup-archive.log'));

        // Verify backups daily at 05:00
        $schedule->command('backup:verify --all')
            ->dailyAt('05:00')
            ->appendOutputTo(storage_path('logs/backup-verify.log'));

        // Clean old backups daily at 06:00
        $schedule->command('backup:clean --force')
            ->dailyAt('06:00')
            ->appendOutputTo(storage_path('logs/backup-clean.log'));

        // Monitor backup health daily at 07:00
        $schedule->command('backup:monitor --notify')
            ->dailyAt('07:00')
            ->appendOutputTo(storage_path('logs/backup-monitor.log'));

        // Stock Reservation Expiry - Every 15 minutes
        $schedule->command('reservation:expire')
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/reservation-expire.log'));
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
