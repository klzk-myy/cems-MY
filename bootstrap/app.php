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
use App\Http\Middleware\TestDashboard;
use App\Http\Middleware\ValidateSignature;
use App\Jobs\Accounting\ReconcileDeferredAccountingJob;
use App\Jobs\Compliance\DownloadEuSanctionsJob;
use App\Jobs\Compliance\DownloadOfacSanctionsJob;
use App\Jobs\Compliance\LowStockAlertJob;
use App\Jobs\Compliance\RunComplianceMonitorJob;
use App\Services\Compliance\CaseManagementService;
use App\Services\Compliance\EddService;
use App\Services\Compliance\KycDocumentExpiryService;
use App\Services\Compliance\Monitors\SanctionsRescreeningMonitor;
use App\Services\Transaction\RateManagementService;
use App\Services\Transaction\TransactionConfirmationService;
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
            // Enforce IP auto-blocking before any further processing.
            IpBlocker::class,
            QueryLogging::class,
            PerformanceTrackingMiddleware::class,
        ]);

        // Global API rate limit (RouteServiceProvider 'api' limiter) runs
        // before everything else in the group.
        $middleware->api(prepend: [
            'throttle:api',
        ]);

        // Enable stateful Sanctum authentication for first-party SPA API requests
        $middleware->api(append: [
            // Blocked IPs short-circuit before the stateful/session wrapper.
            IpBlocker::class,
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
            'test.dashboard' => TestDashboard::class,
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
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/report-msb2.log'));

        // Position Limit - Daily limit utilization check
        $schedule->command('report:position-limit')
            ->dailyAt('06:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/report-position-limit.log'));

        // EOD Reconciliation - End of day reconciliation (runs after counters close)
        $schedule->command('report:eod')
            ->dailyAt('20:00')
            ->withoutOverlapping()
            ->onOneServer()
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
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/report-trial-balance.log'));

        // LMCA - BNM Monthly Form (for previous month) - 1st of month at 00:30
        $schedule->command('report:lmca')
            ->cron('30 0 1 * *')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/report-lmca.log'));

        // Sanctions Rescreening (BNM monthly requirement) - 1st of month at 03:00
        $schedule->command('compliance:rescreen --days=30')
            ->cron('0 3 1 * *')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/compliance-rescreen.log'));

        // Cleanup old temp reports - 1st of month at 02:00
        $schedule->command('reports:cleanup --days=90')
            ->cron('0 2 1 * *')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/reports-cleanup.log'));

        // Quarterly Large Value Report - Run on 1st of months 4, 7, 10, 1 (Apr=4, Jul=7, Oct=10, Jan=1)
        $schedule->command('report:qlvr')
            ->cron('0 1 1 1,4,7,10 *')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/report-qlvr.log'));

        // Report Archival (BNM requires 7-year retention) - January 1st at 04:00
        $schedule->command('reports:archive --months=12')
            ->cron('0 4 1 1 *')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/reports-archive.log'));

        // Revaluation at end of month - last day at 23:59
        $schedule->command('revaluation:run')
            ->cron('59 23 L * *')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/revaluation.log'));

        // Month-End Close - 1st of month at 01:00
        $schedule->command('accounting:month-end')
            ->monthlyOn(1, '01:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/month-end-close.log'));

        // Sanctions Rescreening Monitor - Weekly on Sunday at 02:00
        $schedule->job(new RunComplianceMonitorJob(SanctionsRescreeningMonitor::class))
            ->weeklyOn(0, '02:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/monitor-sanctions-rescreen.log'));

        $schedule->command('reservation:expire')
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/reservation-expire.log'));

        // Failed transaction recovery - every 5 minutes (retry/DLQ sweep)
        $schedule->command('transactions:recover')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/transactions-recover.log'));

        // DLQ admin alert - every 5 minutes, immediately after the recovery sweep
        $schedule->command('transactions:dlq-alert')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/transactions-dlq-alert.log'));

        // EDD expiry - transition Enhanced-Diligence records past their review window to Expired
        $schedule->call(fn () => app(EddService::class)->expireRecords())
            ->name('edd-expire-records')
            ->dailyAt('01:30')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/edd-expire.log'));

        // KYC document expiry - mark verified documents past expiry (plus grace
        // period) as Expired so downstream checks block transactions on them.
        $schedule->call(fn () => app(KycDocumentExpiryService::class)->expireDocuments())
            ->name('kyc-expire-documents')
            ->dailyAt('01:45')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/kyc-document-expire.log'));

        // Notification digest - daily email of unread notifications per user,
        // only when the digest feature is enabled (notifications.digest.enabled).
        if (config('notifications.digest.enabled')) {
            $schedule->command('notifications:send-digest')
                ->dailyAt(config('notifications.digest.time', '09:00'))
                ->withoutOverlapping()
                ->onOneServer()
                ->appendOutputTo(storage_path('logs/notification-digest.log'));
        }

        // EU Consolidated Sanctions - Weekly on Sunday at 02:00
        $schedule->job(new DownloadEuSanctionsJob)
            ->weeklyOn(0, '02:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/sanctions-import-eu.log'));

        // US OFAC SDN List - Weekly on Sunday at 02:00
        $schedule->job(new DownloadOfacSanctionsJob)
            ->weeklyOn(0, '02:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/sanctions-import-ofac.log'));

        // Transaction Confirmation Expiry - Every 15 minutes
        $schedule->call(fn () => app(TransactionConfirmationService::class)->expireStale())
            ->name('confirmation-expire-stale')
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/confirmation-expire.log'));

        // Low Stock Alert - Daily at 06:00
        $schedule->job(new LowStockAlertJob)
            ->dailyAt('06:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/low-stock-alert.log'));

        // Audit Chain Verification - Daily at 03:00
        $schedule->command('audit:verify')
            ->dailyAt('03:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/audit-verify.log'));

        // Queue Health Check - Daily at 05:30
        $schedule->command('queue:health-check')
            ->dailyAt('05:30')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/queue-health-check.log'));

        // IP block statistics snapshot - Daily at 04:45
        // Blocks self-expire via Redis TTL; this records the daily block
        // table for audit and refreshes the blocked-IP index.
        $schedule->command('security:ip stats')
            ->dailyAt('04:45')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/security-ip-stats.log'));

        // Database backup - Daily at 02:00
        $schedule->command('backup:run --type=database')
            ->dailyAt('02:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/backup-database.log'));

        // Full backup - Weekly on Sunday at 03:00
        $schedule->command('backup:clean')
            ->weeklyOn(0, '03:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/backup-clean.log'));

        $schedule->command('backup:run')
            ->weeklyOn(0, '03:05')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/backup-full.log'));

        // Monthly labelled full backup - 1st of month at 04:00
        // Destinations (local + s3) are configured in config/backup.php.
        $schedule->command('backup:run --filename=monthly-full')
            ->monthlyOn(1, '04:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/backup-monthly.log'));

        // Backup health monitoring - Daily at 07:00
        $schedule->command('backup:monitor')
            ->dailyAt('07:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/backup-monitor.log'));

        // Customer risk review sweep - Daily at 02:30
        $schedule->command('customer:risk-review')
            ->dailyAt('02:30')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/customer-risk-review.log'));

        // Audit Log Rotation (BNM 5-year retention) - Weekly on Sunday at 03:30
        $schedule->command('audit:rotate --cleanup')
            ->weeklyOn(0, '03:30')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/audit-rotate.log'));

        // Behavioral Baseline Backfill - Monthly on 1st at 03:30
        // Ensures customers without transaction-driven baselines (e.g.
        // pre-deployment) still get one computed each month.
        $schedule->command('customer:baseline-backfill')
            ->monthlyOn(1, '03:30')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/customer-baseline-backfill.log'));

        // Dormancy Sweep - Monthly on 2nd at 02:30
        // Stamps dormant_at on active customers with no transactions within
        // the configured dormancy window (cems.dormancy_months).
        $schedule->command('customers:mark-dormant')
            ->monthlyOn(2, '02:30')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/customer-mark-dormant.log'));

        // Rate Staleness Check - Hourly (alert when market rates are not refreshed)
        $schedule->command('rates:staleness-check')
            ->hourly()
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/rate-staleness.log'));

        // Optional automatic rate refresh from the upstream API - every 2h.
        // Opt-in via RATE_AUTO_FETCH_ENABLED so deployments without an API
        // key never schedule outbound calls.
        if (config('cems.rate_auto_fetch_enabled')) {
            $schedule->call(fn () => app(RateManagementService::class)->fetchAndStoreRates())
                ->name('rates-auto-fetch')
                ->everyTwoHours()
                ->withoutOverlapping()
                ->onOneServer()
                ->appendOutputTo(storage_path('logs/rates-auto-fetch.log'));
        }

        // Horizon metrics snapshot - Every 5 minutes
        // Feeds the Horizon dashboard's Jobs/Queues trend charts.
        $schedule->command('horizon:snapshot')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/horizon-snapshot.log'));

        // Prune failed jobs older than 7 days - Weekly on Sunday at 04:00
        $schedule->command('queue:prune-failed --hours=168')
            ->weeklyOn(0, '04:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/queue-prune-failed.log'));

        // PEP cessation review - Monthly on the 1st at 04:00
        $schedule->command('customers:pep-cessation-review')
            ->monthlyOn(1, '04:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/pep-cessation-review.log'));

        // Case SLA breach alerts - Daily at 06:00
        $schedule->call(fn () => app(CaseManagementService::class)->alertBreachedCases())
            ->name('case-sla-breach-alerts')
            ->dailyAt('06:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/case-sla-breach-alerts.log'));

        // Due report schedules processor - Hourly (runs user-defined report schedules)
        $schedule->command('reports:process-schedules')
            ->hourly()
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/reports-process-schedules.log'));
    })

    // Disable framework event auto-discovery: Application::configure() co-registers
    // the base Illuminate EventServiceProvider whose discovery pass re-registers
    // every app/Listeners class on top of the explicit $listen map, firing each
    // listener twice.
    ->withEvents(discover: false)
    ->create();

return $app;
