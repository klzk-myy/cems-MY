<?php

use App\Http\Controllers\Accounting\AccountMappingController;
use App\Http\Controllers\Accounting\BudgetController;
use App\Http\Controllers\Accounting\ChartOfAccountsController;
use App\Http\Controllers\Accounting\ExpenseController;
use App\Http\Controllers\Accounting\JournalController;
use App\Http\Controllers\Accounting\PeriodController;
use App\Http\Controllers\Accounting\ReconciliationController;
use App\Http\Controllers\Accounting\ReportController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\ThresholdController;
use App\Http\Controllers\AllocationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BranchClosingController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\BranchPoolController;
use App\Http\Controllers\Compliance\AlertTriageController;
use App\Http\Controllers\Compliance\CaseManagementController;
use App\Http\Controllers\Compliance\EddCustomerController;
use App\Http\Controllers\Compliance\EddReviewController;
use App\Http\Controllers\Compliance\FindingController;
use App\Http\Controllers\Compliance\PepApprovalController;
use App\Http\Controllers\Compliance\RiskDashboardController;
use App\Http\Controllers\Compliance\SanctionListController;
use App\Http\Controllers\Compliance\ScreeningController;
use App\Http\Controllers\Compliance\ScreeningMatchController;
use App\Http\Controllers\Compliance\StrReportController;
use App\Http\Controllers\Compliance\UnifiedAlertController;
use App\Http\Controllers\CounterController;
use App\Http\Controllers\Customer\CustomerSearchController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FiscalYearController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\KycDocumentController;
use App\Http\Controllers\MfaController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationPreferenceController;
use App\Http\Controllers\PerformanceMonitoringController;
use App\Http\Controllers\RateController;
use App\Http\Controllers\Report\AnalyticsController;
use App\Http\Controllers\Report\RegulatoryReportController;
use App\Http\Controllers\ReportScheduleController;
use App\Http\Controllers\RevaluationController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\StockCashController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\System\CurrencyController;
use App\Http\Controllers\SystemAlertController;
use App\Http\Controllers\TestResultsController;
use App\Http\Controllers\Transaction\DlqController;
use App\Http\Controllers\Transaction\TransactionApprovalController;
use App\Http\Controllers\Transaction\TransactionCancellationController;
use App\Http\Controllers\TransactionBatchController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransactionWizardController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VerificationController;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// Laravel health check route (named so it appears in route:list without an unnamed entry)
Route::get('/up', function () {
    event(new DiagnosingHealth);

    return response('');
})->name('up');

// Health check endpoint
Route::get('/health', [HealthCheckController::class, 'index'])
    ->middleware(['auth', 'role:manage_system', 'throttle:60,1'])
    ->name('health');

// Public transaction verification (receipt QR codes). Throttled to slow
// reference enumeration; intentionally outside the auth middleware group.
Route::get('/verify/transaction/{reference}', [VerificationController::class, 'show'])
    ->middleware('throttle:10,1')
    ->name('verification.transaction');

Route::prefix('setup')->name('setup.')->middleware(['setup.accessible'])->group(function () {
    Route::get('/', [SetupController::class, 'index'])->name('index');
    Route::get('/wizard', [SetupController::class, 'wizard'])->name('wizard');
    Route::post('/quick', [SetupController::class, 'quickSetup'])->name('quick');
    Route::post('/step/1', [SetupController::class, 'step1CompanyInfo'])->name('step1');
    Route::post('/step/2', [SetupController::class, 'step2AdminUser'])->name('step2');
    Route::post('/step/3', [SetupController::class, 'step3Currencies'])->name('step3');
    Route::post('/step/4', [SetupController::class, 'step4ExchangeRates'])->name('step4');
    Route::post('/rates/fetch', [SetupController::class, 'fetchRates'])->name('rates.fetch');
    Route::post('/step/5', [SetupController::class, 'step5InitialStock'])->name('step5');
    Route::post('/step/6', [SetupController::class, 'step6OpeningBalance'])->name('step6');
    Route::post('/complete', [SetupController::class, 'completeSetup'])->name('complete');
    Route::get('/status', [SetupController::class, 'checkStatus'])->name('status');
    Route::post('/reset', [SetupController::class, 'resetSetup'])->middleware(['auth', 'role:manage_system', 'password.confirm'])->name('reset');

});

Route::middleware(['auth', 'auth.session', 'session.timeout', 'mfa.enabled'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Forced password rotation (BNM policy) and self-service security.
    Route::get('/password/change', [LoginController::class, 'showChangePassword'])
        ->name('password.change');
    Route::post('/password/change', [LoginController::class, 'changePassword'])
        ->name('password.change.submit')
        ->middleware('throttle:5,1');
    Route::post('/profile/devices/logout-others', [LoginController::class, 'logoutOtherDevices'])
        ->name('profile.devices.logout-others')
        ->middleware('throttle:5,1');

    Route::middleware(['role:access_performance'])->group(function () {
        Route::get('/performance', [PerformanceMonitoringController::class, 'index'])->name('performance');
    });

    Route::prefix('mfa')->name('mfa.')->group(function () {
        Route::get('/setup', [MfaController::class, 'setup'])->name('setup');
        Route::post('/setup', [MfaController::class, 'setupStore'])->name('setup.store');
        Route::get('/verify', [MfaController::class, 'verify'])->name('verify');
        // Code entry endpoints are throttled so TOTP/recovery codes cannot be
        // brute-forced; a per-user counter in MfaService backs this up.
        Route::post('/verify', [MfaController::class, 'verifyStore'])->name('verify.store')
            ->middleware('throttle:5,1');
        Route::post('/disable', [MfaController::class, 'disable'])->name('disable')
            ->middleware('throttle:5,1');
        Route::get('/recovery', [MfaController::class, 'recovery'])->name('recovery');
        Route::post('/recovery/verify', [MfaController::class, 'recoveryVerify'])->name('recovery.verify')
            ->middleware('throttle:5,1');
        Route::get('/recovery-codes', [MfaController::class, 'recoveryCodes'])->name('recovery-codes');
        Route::get('/trusted-devices', [MfaController::class, 'trustedDevices'])->name('trusted-devices');
        Route::post('/recovery-codes/regenerate', [MfaController::class, 'regenerateRecoveryCodes'])
            ->middleware('throttle:5,1')
            ->name('recovery-codes.regenerate');
        Route::delete('/trusted-devices/{deviceId}', [MfaController::class, 'removeDevice'])->name('trusted-devices.remove');
    });

    Route::middleware(['role:access_rates'])->prefix('rates')->name('rates.')->group(function () {
        Route::get('/', [RateController::class, 'index'])->name('index');
        Route::get('/units', [RateController::class, 'units'])->name('units');
        Route::post('/units', [RateController::class, 'updateUnits'])->name('units.update');
        Route::post('/override', [RateController::class, 'override'])->name('override');
        Route::post('/copy-previous', [RateController::class, 'copyPrevious'])->name('copy-previous');
    });

    Route::prefix('transactions')->name('transactions.')->group(function () {
        Route::get('/', [TransactionController::class, 'index'])->name('index')
            ->middleware('role:create_transactions,manage_transactions,approve_transactions');

        Route::get('/wizard', [TransactionWizardController::class, 'index'])->name('wizard')
            ->middleware('role:create_transactions');
        Route::get('/create', [TransactionController::class, 'create'])->name('create')
            ->middleware(['role:create_transactions', 'mfa.verified']);
        Route::post('/', [TransactionController::class, 'store'])->name('store')
            ->middleware(['role:create_transactions', 'mfa.verified']);

        Route::middleware('role:manage_transactions')->group(function () {
            Route::get('/batch-upload', [TransactionBatchController::class, 'showBatchUpload'])->name('batch-upload');
            Route::post('/batch-upload', [TransactionBatchController::class, 'processBatchUpload'])->name('batch-upload.store');
            Route::get('/import/{import}', [TransactionBatchController::class, 'showImportResults'])->name('batch-upload.show');
            Route::get('/template', [TransactionBatchController::class, 'downloadTemplate'])
                ->middleware('mfa.verified')
                ->name('batch-upload.template');
            Route::get('/download-errors/{import}', [TransactionBatchController::class, 'downloadErrors'])
                ->name('batch-upload.download-errors');

            // Registered before /{transaction} so 'export' is not captured
            // as a transaction ID.
            Route::get('/export', [TransactionController::class, 'exportForm'])->name('export.form');
            Route::post('/export', [TransactionController::class, 'export'])->name('export.export')
                ->middleware('password.confirm');
        });

        // Dead letter queue - admin only. Registered before /{transaction} so
        // 'dlq' is not captured as a transaction ID.
        Route::middleware('role:manage_dlq')->group(function () {
            Route::get('/dlq', [DlqController::class, 'index'])->name('dlq');
            Route::post('/dlq/{transaction}/retry', [DlqController::class, 'retry'])->name('dlq.retry');
            Route::post('/dlq/{transaction}/purge', [DlqController::class, 'purge'])->name('dlq.purge');
        });

        Route::get('/{transaction}', [TransactionController::class, 'show'])->name('show');
        Route::get('/{transaction}/receipt', [TransactionController::class, 'receipt'])->name('receipt');
        Route::get('/{transaction}/print', [TransactionController::class, 'receipt'])->name('print');

        Route::post('/{transaction}/approve', [TransactionApprovalController::class, 'approve'])->name('approve')
            ->middleware(['role:approve_transactions', 'mfa.verified']);
        Route::post('/{transaction}/reject', [TransactionApprovalController::class, 'reject'])->name('reject')
            ->middleware(['role:approve_transactions', 'mfa.verified']);
        Route::post('/{transaction}/clear-hold', [TransactionApprovalController::class, 'clearHold'])->name('clear-hold')
            ->middleware(['role:approve_transactions', 'mfa.verified']);
        Route::get('/{transaction}/cancel', [TransactionController::class, 'showCancel'])->name('cancel')
            ->middleware(['role:request_cancellation', 'mfa.verified']);
        Route::post('/{transaction}/cancel', [TransactionCancellationController::class, 'cancel'])->name('cancel.store')
            ->middleware(['role:request_cancellation', 'mfa.verified']);

        Route::get('/{transaction}/confirm', [TransactionApprovalController::class, 'showConfirm'])->name('confirm.show')
            ->middleware('role:approve_transactions');
        Route::post('/{transaction}/confirm', [TransactionApprovalController::class, 'confirm'])->name('confirm.store')
            ->middleware('role:approve_transactions');

        Route::middleware(['role:approve_cancellations', 'mfa.verified'])->group(function () {
            Route::get('/{transaction}/approve-cancellation', [TransactionCancellationController::class, 'showApproveCancel'])
                ->name('approve-cancellation');
            Route::post('/{transaction}/approve-cancellation', [TransactionCancellationController::class, 'approveCancel'])
                ->name('approve-cancellation.store');
            Route::get('/{transaction}/reject-cancellation', [TransactionCancellationController::class, 'showRejectCancel'])
                ->name('reject-cancellation');
            Route::post('/{transaction}/reject-cancellation', [TransactionCancellationController::class, 'rejectCancel'])
                ->name('reject-cancellation.store');
        });
    });

    Route::prefix('customers')->name('customers.')->group(function () {
        Route::get('/', [CustomerController::class, 'index'])->name('index')
            ->middleware('role:create_transactions,manage_customers,access_compliance');
        Route::get('/create', [CustomerController::class, 'create'])->name('create');
        Route::post('/', [CustomerController::class, 'store'])->name('store');
        Route::get('/search', [CustomerSearchController::class, 'search'])->name('search');
        Route::post('/quick-create', [CustomerSearchController::class, 'quickCreate'])->name('quick-create');
        Route::get('/exchange-rates', [CustomerController::class, 'getExchangeRates'])->name('exchange-rates');
        Route::get('/{customer}', [CustomerController::class, 'show'])->name('show');
        Route::get('/{customer}/edit', [CustomerController::class, 'edit'])->name('edit');
        Route::put('/{customer}', [CustomerController::class, 'update'])->name('update');
        Route::post('/{customer}/notes', [CustomerController::class, 'storeNote'])->name('notes.store');
        Route::middleware('role:access_compliance')->group(function () {
            Route::post('/{customer}/freeze', [CustomerController::class, 'freeze'])->name('freeze');
            Route::post('/{customer}/unfreeze', [CustomerController::class, 'unfreeze'])->name('unfreeze');
        });
        Route::middleware('role:manage_customers')->group(function () {
            Route::post('/{customer}/close', [CustomerController::class, 'close'])->name('close');
        });
    });

    Route::prefix('counters')->name('counters.')->group(function () {
        Route::get('/', [CounterController::class, 'index'])->name('index')
            ->middleware('role:operate_counters,manage_counters');

        Route::middleware('role:manage_counters')->group(function () {
            Route::get('/create', [CounterController::class, 'create'])->name('create');
            Route::post('/', [CounterController::class, 'store'])->name('store');
        });

        Route::middleware('role:operate_counters')->group(function () {
            Route::get('/{counter}/open', [CounterController::class, 'showOpen'])->name('open');
            Route::post('/{counter}/open', [CounterController::class, 'open'])->name('open.store');
            Route::get('/{counter}/status', [CounterController::class, 'status'])->name('status');
            Route::get('/{counter}/history', [CounterController::class, 'history'])->name('history');
            Route::get('/{counter}/handover', [CounterController::class, 'showHandover'])->name('handover.show');
            Route::post('/{counter}/handover', [CounterController::class, 'handover'])->name('handover');
            Route::get('/{counter}/handover/acknowledge', [CounterController::class, 'showAcknowledgeHandover'])->name('handover.acknowledge.show');
            Route::post('/{counter}/handover/acknowledge', [CounterController::class, 'acknowledgeHandover'])->name('handover.acknowledge');
        });

        Route::middleware('role:manage_counters')->group(function () {
            Route::get('/{counter}/close', [CounterController::class, 'showClose'])->name('close.show');
            Route::post('/{counter}/close', [CounterController::class, 'close'])->name('close');
            Route::get('/{counter}/emergency', [CounterController::class, 'showEmergency'])->name('emergency');
            Route::post('/{counter}/emergency', [CounterController::class, 'emergency'])->name('emergency.store');
            Route::post('/{counter}/emergency-close', [CounterController::class, 'emergency'])->name('emergency-close');
            Route::get('/{counter}/emergency-closure/{closure}', [CounterController::class, 'showEmergencyClosure'])
                ->name('emergency-closure');
        });
    });

    Route::prefix('stock-cash')->name('stock-cash.')->group(function () {
        Route::get('/', [StockCashController::class, 'index'])->name('index')
            ->middleware('role:manage_stock');
        Route::get('/position/{position}', [StockCashController::class, 'showPosition'])->name('position')
            ->middleware('role:manage_stock');
        Route::get('/till-report', [StockCashController::class, 'tillReport'])->name('till-report')
            ->middleware('role:manage_stock');
        Route::get('/reconciliation', [StockCashController::class, 'reconciliationReport'])->name('reconciliation');
        Route::post('/open', [StockCashController::class, 'openTill'])->name('open')
            ->middleware('role:manage_stock');
        Route::post('/close', [StockCashController::class, 'closeTill'])->name('close')
            ->middleware('role:manage_stock');
    });

    // Allocations (manager/admin)
    Route::middleware('role:manage_allocations')->prefix('allocations')->name('allocations.')->group(function () {
        Route::get('/', [AllocationController::class, 'index'])->name('index');
        Route::get('/create', [AllocationController::class, 'create'])->name('create');
        Route::post('/', [AllocationController::class, 'store'])->name('store');
        Route::get('/{allocation}', [AllocationController::class, 'show'])->name('show');
        Route::post('/{allocation}/approve', [AllocationController::class, 'approve'])->name('approve');
        Route::post('/{allocation}/reject', [AllocationController::class, 'reject'])->name('reject');
        Route::post('/{allocation}/modify', [AllocationController::class, 'modify'])->name('modify');
        Route::post('/{allocation}/return-to-pool', [AllocationController::class, 'returnToPool'])->name('return-to-pool');
    });

    // Teller self-service stock requests
    Route::middleware(['role:request_stock', 'mfa.verified'])->prefix('my-allocations')->name('my-allocations.')->group(function () {
        Route::get('/', [AllocationController::class, 'myIndex'])->name('index');
        Route::get('/request', [AllocationController::class, 'requestForm'])->name('request');
        Route::post('/request', [AllocationController::class, 'submitRequest'])->name('request.store');
        Route::post('/{allocation}/accept', [AllocationController::class, 'accept'])->name('accept');
        Route::post('/{allocation}/return', [AllocationController::class, 'requestReturn'])->name('return');
    });

    // Branch Pools (manager/admin)
    Route::middleware('role:manage_stock')->prefix('branch-pools')->name('branch-pools.')->group(function () {
        Route::get('/', [BranchPoolController::class, 'index'])->name('index');
        Route::post('/', [BranchPoolController::class, 'store'])->name('store');
        Route::get('/{branchPool}', [BranchPoolController::class, 'show'])->name('show');
        Route::post('/{branchPool}/fund', [BranchPoolController::class, 'fund'])->name('fund');
        Route::post('/{branchPool}/debit', [BranchPoolController::class, 'debit'])->name('debit');
    });

    // EOD Dashboard (manager/admin)
    Route::middleware('role:manage_eod')->prefix('eod')->name('eod.')->group(function () {
        Route::get('/', [DashboardController::class, 'eod'])->name('dashboard');
    });

    Route::prefix('stock-transfers')->name('stock-transfers.')->group(function () {
        Route::get('/', [StockTransferController::class, 'index'])->name('index')
            ->middleware('role:manage_stock_transfers');
        Route::get('/create', [StockTransferController::class, 'create'])->name('create')
            ->middleware('role:manage_stock_transfers');
        Route::post('/', [StockTransferController::class, 'store'])->name('store');
        Route::get('/{stockTransfer}', [StockTransferController::class, 'show'])->name('show');

        Route::get('/{stockTransfer}/dispatch', [StockTransferController::class, 'showStep'])->defaults('step', 'dispatch')->name('dispatch.show')
            ->middleware('role:manage_stock_transfers');
        Route::get('/{stockTransfer}/receive', [StockTransferController::class, 'showStep'])->defaults('step', 'receive')->name('receive.show')
            ->middleware('role:manage_stock_transfers');
        Route::get('/{stockTransfer}/approve-bm', [StockTransferController::class, 'showStep'])->defaults('step', 'approve-bm')->name('approve-bm.show')
            ->middleware('role:manage_stock_transfers');
        Route::get('/{stockTransfer}/cancel', [StockTransferController::class, 'showStep'])->defaults('step', 'cancel')->name('cancel.show')
            ->middleware('role:manage_stock_transfers');
        Route::get('/{stockTransfer}/complete', [StockTransferController::class, 'showStep'])->defaults('step', 'complete')->name('complete.show')
            ->middleware('role:manage_stock_transfers');

        Route::post('/{stockTransfer}/approve-bm', [StockTransferController::class, 'approveBm'])->name('approve-bm')
            ->middleware('role:manage_stock_transfers');
        Route::post('/{stockTransfer}/dispatch', [StockTransferController::class, 'dispatch'])->name('dispatch')
            ->middleware('role:manage_stock_transfers');
        Route::post('/{stockTransfer}/receive', [StockTransferController::class, 'receive'])->name('receive')
            ->middleware('role:manage_stock_transfers');
        Route::post('/{stockTransfer}/complete', [StockTransferController::class, 'complete'])->name('complete')
            ->middleware('role:manage_stock_transfers');
        Route::post('/{stockTransfer}/cancel', [StockTransferController::class, 'cancel'])->name('cancel')
            ->middleware('role:manage_stock_transfers');
        Route::post('/{stockTransfer}/reject', [StockTransferController::class, 'reject'])->name('reject')
            ->middleware('role:manage_stock_transfers');
    });

    Route::middleware('role:access_compliance')->group(function () {
        Route::get('/compliance', [DashboardController::class, 'compliance'])->name('compliance');
        Route::get('/compliance/flagged', [DashboardController::class, 'compliance'])->name('compliance.flagged');
        Route::patch('/compliance/flags/{flaggedTransaction}/assign', [DashboardController::class, 'assignFlag'])->name('compliance.flags.assign');
        Route::patch('/compliance/flags/{flaggedTransaction}/resolve', [DashboardController::class, 'resolveFlag'])->name('compliance.flags.resolve');

        Route::prefix('compliance/alerts')->name('compliance.alerts.')->group(function () {
            Route::get('/', [AlertTriageController::class, 'index'])->name('index');
            Route::post('/bulk-assign', [AlertTriageController::class, 'bulkAssign'])->name('bulk-assign');
            Route::post('/bulk-resolve', [AlertTriageController::class, 'bulkResolve'])->name('bulk-resolve');
            Route::post('/auto-assign', [AlertTriageController::class, 'autoAssign'])->name('auto-assign');
            Route::get('/{alert}', [AlertTriageController::class, 'show'])->name('show');
            Route::post('/{alert}/assign', [AlertTriageController::class, 'assign'])->name('assign');
            Route::post('/{alert}/resolve', [AlertTriageController::class, 'resolve'])->name('resolve');
            Route::post('/{alert}/dismiss', [AlertTriageController::class, 'dismiss'])->name('dismiss');
            Route::post('/{alert}/escalate', [AlertTriageController::class, 'escalate'])->name('escalate');
        });

        Route::get('/compliance/unified', [UnifiedAlertController::class, 'index'])->name('compliance.unified.index');

        Route::prefix('compliance/cases')->name('compliance.cases.')->group(function () {
            Route::get('/', [CaseManagementController::class, 'index'])->name('index');
            Route::post('/', [CaseManagementController::class, 'store'])->name('store');
            Route::get('/{case}', [CaseManagementController::class, 'show'])->name('show');
            Route::patch('/{case}', [CaseManagementController::class, 'update'])->name('update');
            Route::post('/{case}/merge', [CaseManagementController::class, 'merge'])->name('merge');
            Route::post('/{case}/link-alert', [CaseManagementController::class, 'linkAlert'])->name('link-alert');
            Route::post('/{case}/escalate', [CaseManagementController::class, 'escalate'])->name('escalate');
            Route::post('/{case}/notes', [CaseManagementController::class, 'addNote'])->name('notes.store');
            Route::post('/{case}/documents', [CaseManagementController::class, 'uploadDocument'])->name('documents.upload');
            Route::post('/{case}/documents/{document}/verify', [CaseManagementController::class, 'verifyDocument'])->name('documents.verify');
            Route::post('/{case}/links', [CaseManagementController::class, 'addLink'])->name('links.add');
            Route::delete('/{case}/links/{link}', [CaseManagementController::class, 'removeLink'])->name('links.remove');
        });

        Route::prefix('compliance/sanctions')->name('compliance.sanctions.')->group(function () {
            Route::get('/', [SanctionListController::class, 'index'])->name('index');
            Route::get('/entries', [SanctionListController::class, 'entriesIndex'])->name('entries.index');
            Route::get('/entries/create', [SanctionListController::class, 'createEntry'])->name('entries.create');
            Route::post('/entries', [SanctionListController::class, 'storeEntry'])->name('entries.store');
            Route::get('/entries/{entry}', [SanctionListController::class, 'showEntry'])->name('entries.show');
            Route::get('/entries/{entry}/edit', [SanctionListController::class, 'editEntry'])->name('entries.edit');
            Route::put('/entries/{entry}', [SanctionListController::class, 'updateEntry'])->name('entries.update');
            Route::get('/import-logs', [SanctionListController::class, 'importLogs'])->name('import-logs');
            Route::get('/{list}', [SanctionListController::class, 'show'])->name('show');
            // Throttle matches the API route (5 imports / 10 min): each call
            // downloads and imports an external list synchronously.
            Route::post('/{list}/import', [SanctionListController::class, 'triggerImport'])
                ->name('import')
                ->middleware('throttle:5,10');
        });

        Route::prefix('compliance/screening')->name('compliance.screening.')->group(function () {
            Route::get('/{customerId}', [ScreeningController::class, 'show'])->name('show');
            Route::post('/{customerId}', [ScreeningController::class, 'screen'])->name('screen');
            Route::get('/{customerId}/history', [ScreeningController::class, 'history'])->name('history');
            Route::get('/{customerId}/status', [ScreeningController::class, 'status'])->name('status');
        });

        Route::prefix('compliance/screening-matches')->name('compliance.screening.matches.')->group(function () {
            Route::get('/', [ScreeningMatchController::class, 'index'])->name('index');
            Route::get('/{resultId}', [ScreeningMatchController::class, 'show'])->name('show');
            Route::post('/{resultId}/confirm', [ScreeningMatchController::class, 'confirm'])->name('confirm');
            Route::post('/{resultId}/dismiss', [ScreeningMatchController::class, 'dismiss'])->name('dismiss');
        });

        Route::prefix('compliance/findings')->name('compliance.findings.')->group(function () {
            Route::get('/', [FindingController::class, 'index'])->name('index');
            Route::get('/{id}', [FindingController::class, 'show'])->name('show');
            Route::post('/{id}/dismiss', [FindingController::class, 'dismiss'])->name('dismiss');
            Route::post('/{id}/create-case', [FindingController::class, 'createCase'])->name('create-case');
        });

        // STR filings (pd-00 s22): list/detail/draft/submit/acknowledge/export
        Route::middleware('role:access_compliance')->prefix('str')->name('compliance.str.')->group(function () {
            Route::get('/', [StrReportController::class, 'index'])->name('index');
            Route::get('/export', [StrReportController::class, 'exportCsv'])->name('export')
                ->middleware('password.confirm');
            Route::get('/{strReport}', [StrReportController::class, 'show'])->name('show');
            Route::post('/from-case/{case}', [StrReportController::class, 'createFromCase'])->name('create-from-case');
            Route::patch('/{strReport}/submit', [StrReportController::class, 'submit'])->name('submit');
            Route::patch('/{strReport}/acknowledge', [StrReportController::class, 'acknowledge'])->name('acknowledge');
        });
    });

    // EDD staff review (mirrors the Api/V1 EddController approve/reject
    // logic; prefix avoids colliding with the signed customer portal at
    // compliance/edd/*). Accessible to both Compliance Officers and Admins.
    Route::middleware('role:access_compliance')->prefix('compliance/edd-review')->name('compliance.edd-reviews.')->group(function () {
        Route::get('/', [EddReviewController::class, 'index'])->name('index');
        Route::get('/records/{eddRecord}', [EddReviewController::class, 'show'])->name('show');
        Route::post('/records/{eddRecord}/approve', [EddReviewController::class, 'approve'])->name('approve');
        Route::post('/records/{eddRecord}/reject', [EddReviewController::class, 'reject'])->name('reject');
    });

    // Risk dashboard is gated by the view_risk_dashboard matrix permission
    // (managers/admins by default) at both the route and the controller
    // (RiskDashboardController::requirePermission). Granting
    // view_risk_dashboard to another role via /admin/role-permissions opens
    // both gates at once. Rescreen stays restricted by manage_risk_screening.
    Route::middleware('role:view_risk_dashboard')->prefix('compliance/risk-dashboard')->name('compliance.risk-dashboard.')->group(function () {
        Route::get('/', [RiskDashboardController::class, 'index'])->name('index');
        Route::get('/customer/{customer}', [RiskDashboardController::class, 'customer'])->name('customer');
        Route::get('/trends', [RiskDashboardController::class, 'trends'])->name('trends');
        Route::post('/rescreen', [RiskDashboardController::class, 'rescreen'])->name('rescreen')
            ->middleware('role:manage_risk_screening');
    });

    // PEP sign-off (pd-00.md 14C.13.1(d)) — compliance officer decisions on
    // pending PepApprovalRequests raised by TransactionValidationService.
    // POST-only so state changes stay CSRF-protected.
    Route::middleware('role:access_compliance')->prefix('compliance/pep-approvals')->name('compliance.pep-approvals.')->group(function () {
        Route::get('/', [PepApprovalController::class, 'index'])->name('index');
        Route::post('/{pepApproval}/approve', [PepApprovalController::class, 'approve'])->name('approve');
        Route::post('/{pepApproval}/reject', [PepApprovalController::class, 'reject'])->name('reject');
    });

    Route::middleware('role:access_accounting')->prefix('accounting')->name('accounting.')->group(function () {
        Route::get('/', [DashboardController::class, 'accounting'])->name('index');

        // Journal Entry Management
        Route::get('/journal', [JournalController::class, 'index'])->name('journal');
        Route::get('/journal/create', [JournalController::class, 'create'])->name('journal.create');
        Route::post('/journal', [JournalController::class, 'store'])->name('journal.store');
        Route::get('/journal/{entry}', [JournalController::class, 'show'])->name('journal.show');
        Route::post('/journal/{entry}/reverse', [JournalController::class, 'reverse'])->name('journal.reverse');

        // Petty cash / branch expenses — posting is direct, no approval.
        Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::get('/expenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
        Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::post('/expenses/fund', [ExpenseController::class, 'fund'])->name('expenses.fund');

        // Ledger & Reports
        Route::get('/ledger', [ReportController::class, 'ledger'])->name('ledger');
        Route::get('/ledger/{accountCode}', [ReportController::class, 'ledgerAccount'])->name('ledger.account');

        Route::get('/trial-balance', [ReportController::class, 'trialBalance'])->name('trial-balance');
        Route::get('/profit-loss', [ReportController::class, 'profitLoss'])->name('profit-loss');
        Route::get('/balance-sheet', [ReportController::class, 'balanceSheet'])->name('balance-sheet');
        Route::get('/cash-flow', [ReportController::class, 'cashFlow'])->name('cash-flow');
        Route::get('/ratios', [ReportController::class, 'ratios'])->name('ratios');

        // Periods & Fiscal Years
        Route::get('/periods', [PeriodController::class, 'periods'])->name('periods');
        Route::post('/periods/{period}/close', [PeriodController::class, 'closePeriod'])->name('period.close');
        Route::get('/fiscal-years', [FiscalYearController::class, 'list'])->name('fiscal-years');
        Route::post('/fiscal-years', [FiscalYearController::class, 'store'])->name('fiscal-years.store');
        Route::post('/fiscal-years/{year}/close', [FiscalYearController::class, 'close'])->name('fiscal-years.close');
        Route::get('/revaluation', [RevaluationController::class, 'index'])->name('revaluation');
        Route::get('/revaluation/history', [RevaluationController::class, 'history'])->name('revaluation.history');
        Route::post('/revaluation/run', [RevaluationController::class, 'run'])->name('revaluation.run');

        // Bank Reconciliation
        Route::get('/reconciliation', [ReconciliationController::class, 'index'])->name('reconciliation');
        Route::get('/reconciliation/report', [ReconciliationController::class, 'reconciliationReport'])->name('reconciliation.report');
        Route::post('/reconciliation/import', [ReconciliationController::class, 'importBankStatement'])->name('reconciliation.import');
        Route::post('/reconciliation/{reconciliation}/exception', [ReconciliationController::class, 'markAsException'])->name('reconciliation.exception');
        Route::post('/reconciliation/{reconciliation}/match', [ReconciliationController::class, 'manualMatch'])->name('reconciliation.match');
        Route::post('/reconciliation/{reconciliation}/unmatch', [ReconciliationController::class, 'unmatch'])->name('reconciliation.unmatch');
        Route::get('/reconciliation/export', [ReconciliationController::class, 'exportReconciliation'])->name('reconciliation.export')
            ->middleware('password.confirm');

        // Budget
        Route::get('/budget', [BudgetController::class, 'index'])->name('budget');
        Route::post('/budget', [BudgetController::class, 'store'])->name('budget.store');
        Route::patch('/budget/{budget}', [BudgetController::class, 'update'])->name('budget.update');

        // Account Mappings — admin/accountant UI over the account_mappings
        // table. Nested inside access_accounting so every mapping editor also
        // holds accounting access; the POST steps up with password.confirm
        // like other admin mutations.
        Route::middleware(['role:manage_account_mappings', 'mfa.verified'])->group(function () {
            Route::get('/mappings', [AccountMappingController::class, 'index'])->name('mappings.index');
            Route::post('/mappings', [AccountMappingController::class, 'update'])->name('mappings.update')
                ->middleware('password.confirm');
            Route::post('/mappings/provision/{currency}', [AccountMappingController::class, 'provision'])->name('mappings.provision')
                ->middleware('password.confirm');
        });
    });

    Route::middleware('role:view_reports')->prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [DashboardController::class, 'reports'])->name('index');

        Route::get('/msb2', [RegulatoryReportController::class, 'msb2'])->name('msb2');
        Route::post('/msb2/export', [RegulatoryReportController::class, 'msb2Generate'])->name('msb2.export')
            ->middleware('password.confirm');
        Route::get('/lmca', [RegulatoryReportController::class, 'lmca'])->name('lmca');
        Route::post('/lmca/export', [RegulatoryReportController::class, 'lmcaGenerate'])->name('lmca.export')
            ->middleware('password.confirm');
        Route::get('/quarterly-lvr', [RegulatoryReportController::class, 'quarterlyLvr'])->name('quarterly-lvr');
        Route::post('/quarterly-lvr/export', [RegulatoryReportController::class, 'quarterlyLvrGenerate'])
            ->name('quarterly-lvr.export')
            ->middleware('password.confirm');
        Route::get('/position-limit', [RegulatoryReportController::class, 'positionLimit'])->name('position-limit');
        Route::post('/position-limit/export', [RegulatoryReportController::class, 'positionLimitGenerate'])
            ->name('position-limit.export')
            ->middleware('password.confirm');

        Route::get('/monthly-trends', [AnalyticsController::class, 'monthlyTrends'])->name('monthly-trends');
        Route::get('/profitability', [AnalyticsController::class, 'profitability'])->name('profitability');
        Route::get('/customer-analysis', [AnalyticsController::class, 'customerAnalysis'])->name('customer-analysis');
        Route::get('/compliance-summary', [AnalyticsController::class, 'complianceSummary'])->name('compliance-summary');
    });

    Route::middleware(['role:manage_users', 'mfa.verified'])->prefix('users')->name('users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('/create', [UserController::class, 'create'])->name('create');
        Route::post('/', [UserController::class, 'store'])->name('store')->middleware('password.confirm');
        Route::get('/{user}', [UserController::class, 'show'])->name('show');
        Route::get('/{user}/edit', [UserController::class, 'edit'])->name('edit');
        Route::put('/{user}', [UserController::class, 'update'])->name('update')->middleware('password.confirm');
        Route::post('/{user}/reset-password', [UserController::class, 'resetPassword'])->name('reset-password')->middleware('password.confirm');
    });

    // Role Permission Management — admin-only UI for the dynamic
    // role-permission matrix (tick/untick privileges per role).
    Route::middleware(['role:manage_role_permissions', 'mfa.verified'])->prefix('admin/role-permissions')->name('admin.role-permissions.')->group(function () {
        Route::get('/', [RolePermissionController::class, 'index'])->name('index');
        Route::post('/', [RolePermissionController::class, 'update'])->name('update')->middleware('password.confirm');
    });

    // Threshold Management — admin UI over the audited threshold system.
    // Views effective values vs config defaults and writes DB overrides via
    // ThresholdService; both mutations step up with password confirmation.
    Route::middleware(['role:manage_thresholds', 'mfa.verified'])->prefix('admin/thresholds')->name('admin.thresholds.')->group(function () {
        Route::get('/', [ThresholdController::class, 'index'])->name('index');
        Route::post('/', [ThresholdController::class, 'update'])->name('update')->middleware('password.confirm');
        Route::post('/reset', [ThresholdController::class, 'reset'])->name('reset')->middleware('password.confirm');
    });

    Route::middleware(['role:access_branches'])->prefix('branches')->name('branches.')->group(function () {
        // Branch management CRUD (plan WS-C2) - wraps the same BranchService
        // used by Api/V1/BranchController so both paths share one business rule set.
        // Managers can view and edit their own branch; admins have full access.
        Route::get('/', [BranchController::class, 'index'])->name('index');
        Route::get('/{branch}/edit', [BranchController::class, 'edit'])->name('edit');
        Route::put('/{branch}', [BranchController::class, 'update'])->name('update');
    });

    Route::middleware(['role:manage_branches'])->prefix('branches')->name('branches.')->group(function () {
        Route::get('/create', [BranchController::class, 'create'])->name('create');
        Route::post('/', [BranchController::class, 'store'])->name('store');
        Route::post('/{branch}/deactivate', [BranchController::class, 'deactivate'])->name('deactivate');
    });

    // Branch Closing Workflow — same surface as the API closing workflow,
    // gated by manage_branch_closing (manager default). The sidebar links to
    // 'closing.show' with no branch parameter, so it resolves the user's
    // branch and forwards to the per-branch page below.
    Route::get('/closing', [BranchClosingController::class, 'index'])
        ->middleware('role:manage_branch_closing')
        ->name('closing.show');

    Route::middleware(['role:manage_branch_closing'])->prefix('branches')->name('branches.')->group(function () {
        Route::get('/{branch}/closing', [BranchClosingController::class, 'show'])
            ->name('closing.show');
        Route::post('/{branch}/closing/initiate', [BranchClosingController::class, 'initiate'])
            ->name('closing.initiate');
        Route::post('/{branch}/closing/settle', [BranchClosingController::class, 'settle'])
            ->name('closing.settle');
        Route::post('/{branch}/closing/finalize', [BranchClosingController::class, 'finalize'])
            ->name('closing.finalize');
    });

    // Currency management (plan WS-C1). Currencies are seeded during setup;
    // this surface allows post-setup creation and soft-disabling. Disabling
    // is guarded against open transactions and non-zero currency positions.
    Route::middleware(['role:manage_currencies'])->prefix('system/currencies')->name('system.currencies.')->group(function () {
        Route::get('/', [CurrencyController::class, 'index'])->name('index');
        Route::get('/create', [CurrencyController::class, 'create'])->name('create');
        Route::post('/', [CurrencyController::class, 'store'])->name('store');
        Route::get('/{currency}/edit', [CurrencyController::class, 'edit'])->name('edit');
        Route::put('/{currency}', [CurrencyController::class, 'update'])->name('update');

        Route::post('/{currency}/disable', [CurrencyController::class, 'disable'])->name('disable');
        Route::post('/{currency}/enable', [CurrencyController::class, 'enable'])->name('enable');
    });

    // Read-only chart of accounts viewer (plan WS-C3). Registered outside the
    // accounting group so Compliance Officers can inspect the COA; accountants
    // and managers reach it via access_accounting. Balances come from
    // LedgerService::getTrialBalance as of today.
    Route::middleware('role:access_compliance,access_accounting')->get('accounting/chart-of-accounts', [ChartOfAccountsController::class, 'index'])
        ->name('accounting.chart-of-accounts.index');

    Route::middleware(['role:view_test_results', 'test.dashboard'])->prefix('test-results')->name('test-results.')->group(function () {
        Route::get('/compare', [TestResultsController::class, 'compare'])->name('compare');
        Route::get('/', [TestResultsController::class, 'index'])->name('index');
        Route::get('/statistics', [TestResultsController::class, 'statistics'])->name('statistics');
        Route::get('/status', [TestResultsController::class, 'latestStatus'])->name('status');
        Route::post('/run', [TestResultsController::class, 'run'])->name('run');
        Route::get('/{testResult}', [TestResultsController::class, 'show'])->name('show');
        Route::post('/cleanup', [TestResultsController::class, 'cleanup'])->name('cleanup');
        Route::get('/{testResult}/output', [TestResultsController::class, 'output'])->name('output');
    });

    // System alerts - admin only. Alerts are operations-sensitive (they
    // describe failing subsystems), so branch-level staff must not see them.
    Route::middleware('role:manage_system_alerts')->prefix('system/alerts')->name('system.alerts.')->group(function () {
        Route::get('/', [SystemAlertController::class, 'index'])->name('index');
        // State changes are POST-only (CSRF-protected). The emailed link
        // targets the GET confirmation page, which submits this POST route.
        Route::get('/{alert}/acknowledge', [SystemAlertController::class, 'showAcknowledge'])->name('acknowledge.show');
        Route::post('/{alert}/acknowledge', [SystemAlertController::class, 'acknowledge'])->name('acknowledge');
    });

    // Audit log viewer. The route gate mirrors SystemLogPolicy, which grants
    // viewAny/view to Admin and Compliance Officer roles.
    Route::middleware('role:access_compliance')->prefix('admin/audit-logs')->name('admin.audit-logs.')->group(function () {
        Route::get('/', [AuditLogController::class, 'index'])->name('index');
        Route::get('/{log}', [AuditLogController::class, 'show'])->name('show');
    });

    // Notifications - the header bell. Any authenticated user manages only
    // their own in-app notifications; the unread-count endpoint feeds the
    // bell's live badge polling.
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/preferences', [NotificationPreferenceController::class, 'show'])->name('preferences');
        Route::post('/preferences', [NotificationPreferenceController::class, 'update'])->name('preferences.update');
        Route::post('/read-all', [NotificationController::class, 'markAllRead'])->name('read-all');
        Route::post('/{notification}/read', [NotificationController::class, 'markRead'])->name('read');
        Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
    });

    // EDD Customer Portal (signed URLs — no auth required)
    Route::prefix('compliance/edd')->name('compliance.edd.customer.')->group(function () {
        Route::get('/', [EddCustomerController::class, 'index'])->name('index');
        Route::get('/records/{eddRecord}', [EddCustomerController::class, 'show'])->name('show');
        Route::post('/documents/{eddDocumentRequest}/upload', [EddCustomerController::class, 'upload'])->name('upload');
        Route::get('/documents/{eddDocumentRequest}/download', [EddCustomerController::class, 'download'])->name('download');
    });

    // Report Schedules (admin)
    Route::middleware('role:manage_report_schedules')->prefix('reports/schedules')->name('reports.schedules.')->group(function () {
        Route::get('/', [ReportScheduleController::class, 'index'])->name('index');
        Route::get('/create', [ReportScheduleController::class, 'create'])->name('create');
        Route::post('/', [ReportScheduleController::class, 'store'])->name('store');
        Route::get('/{schedule}', [ReportScheduleController::class, 'show'])->name('show');
        Route::post('/{schedule}/pause', [ReportScheduleController::class, 'pause'])->name('pause');
        Route::post('/{schedule}/resume', [ReportScheduleController::class, 'resume'])->name('resume');
        Route::get('/{schedule}/edit', [ReportScheduleController::class, 'edit'])->name('edit');
        Route::put('/{schedule}', [ReportScheduleController::class, 'update'])->name('update');
        Route::delete('/{schedule}', [ReportScheduleController::class, 'destroy'])->name('destroy');
    });

    // KYC Documents (compliance/admin)
    Route::middleware('role:access_compliance')->prefix('kyc-documents')->name('kyc-documents.')->group(function () {
        Route::post('/{customerDocument}/verify', [KycDocumentController::class, 'verify'])->name('verify');
        Route::post('/{customerDocument}/reject', [KycDocumentController::class, 'reject'])->name('reject');
        Route::get('/{customerDocument}/download', [KycDocumentController::class, 'download'])->name('download');
    });
});

require __DIR__.'/auth.php';
