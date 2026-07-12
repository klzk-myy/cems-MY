<?php

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsureBranchScope;
use App\Http\Middleware\EnsureMfaVerified;
use App\Http\Middleware\EnsureSetupAccessible;
use App\Http\Middleware\IpBlocker;
use App\Http\Middleware\PerformanceTrackingMiddleware;
use App\Http\Middleware\QueryLogging;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SessionTimeout;
use App\Http\Middleware\StrictRateLimit;
use App\Http\Middleware\ValidateSignature;
use App\Jobs\Accounting\ReconcileDeferredAccountingJob;
use App\Jobs\Compliance\CounterfeitAlertJob;
use App\Jobs\Compliance\CurrencyFlowJob;
use App\Jobs\Compliance\CustomerLocationAnomalyJob;
use App\Jobs\Compliance\SanctionsRescreeningJob;
use App\Jobs\Compliance\StructuringMonitorJob;
use App\Jobs\Compliance\VelocityMonitorJob;
use App\Jobs\ImportSanctionsJob;
use App\Jobs\RescreenHighRiskCustomersJob;
use Illuminate\Auth\Middleware\AuthenticateWithBasicAuth;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Session\Middleware\AuthenticateSession;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Global middleware
        $middleware->web(append: [
            SecurityHeaders::class,
            QueryLogging::class,
            PerformanceTrackingMiddleware::class,
        ]);

        // Enable stateful Sanctum authentication for first-party SPA API requests
        $middleware->api(append: [
            EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->alias([
            'auth' => Authenticate::class,
            'auth.basic' => AuthenticateWithBasicAuth::class,
            'auth.session' => AuthenticateSession::class,
            'branch.scope' => EnsureBranchScope::class,
            'cache.headers' => SetCacheHeaders::class,
            'can' => Authorize::class,
            'guest' => RedirectIfAuthenticated::class,
            'password.confirm' => RequirePassword::class,
            'precognitive' => HandlePrecognitiveRequests::class,
            'signed' => ValidateSignature::class,
            'throttle' => ThrottleRequests::class,
            'verified' => EnsureEmailIsVerified::class,
            'role' => CheckRole::class,
            'mfa.verified' => EnsureMfaVerified::class,
            'session.timeout' => SessionTimeout::class,
            'security.headers' => SecurityHeaders::class,
            'ip.blocker' => IpBlocker::class,
            'strict.ratelimit' => StrictRateLimit::class,
            'setup.accessible' => EnsureSetupAccessible::class,
            'stateful' => EnsureFrontendRequestsAreStateful::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->withSchedule(function (Schedule $schedule) {
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

        // Trial Balance - Every Sunday at 01:00
        $schedule->command('report:trial-balance')
            ->weekly()
            ->sundays()
            ->at('01:00')
            ->appendOutputTo(storage_path('logs/report-trial-balance.log'));

        // LMCA - BNM Monthly Form (for previous month) - 1st of month at 00:30
        $schedule->command('report:lmca')
            ->cron('30 0 1 * *')
            ->appendOutputTo(storage_path('logs/report-lmca.log'));

        // Sanctions Rescreening (BNM monthly requirement) - 1st of month at 03:00
        $schedule->command('compliance:rescreen --days=30')
            ->cron('0 3 1 * *')
            ->appendOutputTo(storage_path('logs/compliance-rescreen.log'));

        // Cleanup old temp reports - 1st of month at 02:00
        $schedule->command('reports:cleanup --days=90')
            ->cron('0 2 1 * *')
            ->appendOutputTo(storage_path('logs/reports-cleanup.log'));

        // Quarterly Large Value Report - Run on 1st of months 4, 7, 10, 1 (Apr=4, Jul=7, Oct=10, Jan=1)
        $schedule->command('report:qlvr')
            ->cron('0 1 1 1,4,7,10 *')
            ->appendOutputTo(storage_path('logs/report-qlvr.log'));

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

        // Daily sanctions list update at 03:00 (BNM requires within 24 hours)
        $schedule->command('sanctions:update')
            ->dailyAt('03:00')
            ->appendOutputTo(storage_path('logs/sanctions-update.log'));

        // Check sanctions status and alert if failed
        $schedule->command('sanctions:status')
            ->dailyAt('08:00')
            ->appendOutputTo(storage_path('logs/sanctions-status-check.log'));

        // UN Consolidated sanctions list - Daily at 1 AM
        // Uses lazy-resolved slug to avoid eager DB queries at app boot
        $schedule->job(new ImportSanctionsJob(listSlug: 'un_consolidated'))
            ->dailyAt('01:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/sanctions-import-un.log'));

        // MOHA Malaysia sanctions list - Weekly on Sunday at 2 AM
        $schedule->job(new ImportSanctionsJob(listSlug: 'moha_malaysia'))
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
    })
    ->create();

return $app;
