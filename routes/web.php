<?php

use App\Http\Controllers\Accounting\BudgetController;
use App\Http\Controllers\Accounting\JournalController;
use App\Http\Controllers\Accounting\ReconciliationController;
use App\Http\Controllers\Accounting\ReportController;
use App\Http\Controllers\AccountingController;
use App\Http\Controllers\BranchClosingController;
use App\Http\Controllers\Compliance\AlertTriageController;
use App\Http\Controllers\Compliance\CaseManagementController;
use App\Http\Controllers\Compliance\FindingController;
use App\Http\Controllers\Compliance\RiskDashboardController;
use App\Http\Controllers\Compliance\SanctionListController;
use App\Http\Controllers\Compliance\ScreeningController;
use App\Http\Controllers\Compliance\UnifiedAlertController;
use App\Http\Controllers\CounterController;
use App\Http\Controllers\Customer\CustomerSearchController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FiscalYearController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MfaController;
use App\Http\Controllers\PerformanceMonitoringController;
use App\Http\Controllers\RateController;
use App\Http\Controllers\Report\AnalyticsController;
use App\Http\Controllers\Report\RegulatoryReportController;
use App\Http\Controllers\RevaluationController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\StockCashController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\TestQueryLogController;
use App\Http\Controllers\TestResultsController;
use App\Http\Controllers\Transaction\TransactionApprovalController;
use App\Http\Controllers\Transaction\TransactionCancellationController;
use App\Http\Controllers\TransactionBatchController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureSetupAccessible;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// Health check endpoint
Route::get('/health', [HealthCheckController::class, 'index'])
    ->middleware(['auth', 'throttle:60,1'])
    ->name('health');

Route::middleware(['auth', 'role:admin'])->get('/test/query-log', [TestQueryLogController::class, 'index']);

Route::prefix('setup')->name('setup.')->middleware([EnsureSetupAccessible::class])->group(function () {
    Route::get('/', [SetupController::class, 'index'])->name('index');
    Route::get('/wizard', [SetupController::class, 'wizard'])->name('wizard');
    Route::post('/quick', [SetupController::class, 'quickSetup'])->name('quick');
    Route::post('/step/1', [SetupController::class, 'step1CompanyInfo'])->name('step1');
    Route::post('/step/2', [SetupController::class, 'step2AdminUser'])->name('step2');
    Route::post('/step/3', [SetupController::class, 'step3Currencies'])->name('step3');
    Route::post('/step/4', [SetupController::class, 'step4ExchangeRates'])->name('step4');
    Route::post('/step/5', [SetupController::class, 'step5InitialStock'])->name('step5');
    Route::post('/step/6', [SetupController::class, 'step6OpeningBalance'])->name('step6');
    Route::post('/complete', [SetupController::class, 'completeSetup'])->name('complete');
    Route::get('/status', [SetupController::class, 'checkStatus'])->name('status');
    Route::post('/reset', [SetupController::class, 'resetSetup'])->name('reset');
});

Route::middleware(['auth', 'session.timeout'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::middleware(['role:manager'])->group(function () {
        Route::get('/performance', [PerformanceMonitoringController::class, 'index'])->name('performance');
    });

    Route::prefix('mfa')->name('mfa.')->group(function () {
        Route::get('/setup', [MfaController::class, 'setup'])->name('setup');
        Route::post('/setup', [MfaController::class, 'setupStore'])->name('setup.store');
        Route::get('/verify', [MfaController::class, 'verify'])->name('verify');
        Route::post('/verify', [MfaController::class, 'verifyStore'])->name('verify.store');
        Route::post('/disable', [MfaController::class, 'disable'])->name('disable');
        Route::get('/recovery', [MfaController::class, 'recovery'])->name('recovery');
        Route::post('/recovery/verify', [MfaController::class, 'recoveryVerify'])->name('recovery.verify');
        Route::get('/recovery-codes', [MfaController::class, 'recoveryCodes'])->name('recovery-codes');
        Route::get('/trusted-devices', [MfaController::class, 'trustedDevices'])->name('trusted-devices');
        Route::delete('/trusted-devices/{deviceId}', [MfaController::class, 'removeDevice'])->name('trusted-devices.remove');
    });

    Route::middleware(['role:manager'])->prefix('rates')->name('rates.')->group(function () {
        Route::get('/', [RateController::class, 'index'])->name('index');
    });

    Route::post('/rates/override', [RateController::class, 'override'])->name('rates.override')->middleware('role:manager,admin');

    Route::prefix('transactions')->name('transactions.')->group(function () {
        Route::get('/', [TransactionController::class, 'index'])->name('index');

        Route::get('/create', [TransactionController::class, 'create'])->name('create')
            ->middleware('mfa.verified');
        Route::post('/', [TransactionController::class, 'store'])->name('store')
            ->middleware('mfa.verified');

        Route::middleware('role:manager')->group(function () {
            Route::get('/batch-upload', [TransactionBatchController::class, 'showBatchUpload'])->name('batch-upload');
            Route::post('/batch-upload', [TransactionBatchController::class, 'processBatchUpload'])->name('batch-upload.store');
            Route::get('/import/{import}', [TransactionBatchController::class, 'showImportResults'])->name('batch-upload.show');
            Route::get('/template', [TransactionBatchController::class, 'downloadTemplate'])->name('batch-upload.template');
            Route::get('/download-errors/{import}', [TransactionBatchController::class, 'downloadErrors'])
                ->name('batch-upload.download-errors');
        });

        Route::get('/{transaction}', [TransactionController::class, 'show'])->name('show');
        Route::get('/{transaction}/receipt', [TransactionController::class, 'receipt'])->name('receipt');
        Route::get('/{transaction}/print', [TransactionController::class, 'receipt'])->name('print');

        Route::post('/{transaction}/approve', [TransactionApprovalController::class, 'approve'])->name('approve')
            ->middleware(['role:manager', 'mfa.verified']);
        Route::post('/{transaction}/reject', [TransactionApprovalController::class, 'reject'])->name('reject')
            ->middleware(['role:manager', 'mfa.verified']);
        Route::get('/{transaction}/cancel', [TransactionController::class, 'showCancel'])->name('cancel')
            ->middleware(['role:manager', 'mfa.verified']);
        Route::post('/{transaction}/cancel', [TransactionCancellationController::class, 'cancel'])->name('cancel.store')
            ->middleware(['role:manager', 'mfa.verified']);

        Route::get('/{transaction}/confirm', [TransactionApprovalController::class, 'showConfirm'])->name('confirm.show')
            ->middleware('role:manager');
        Route::post('/{transaction}/confirm', [TransactionApprovalController::class, 'confirm'])->name('confirm.store')
            ->middleware('role:manager');

        Route::middleware(['role:manager', 'mfa.verified'])->group(function () {
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
        Route::get('/', [CustomerController::class, 'index'])->name('index');
        Route::get('/create', [CustomerController::class, 'create'])->name('create');
        Route::post('/', [CustomerController::class, 'store'])->name('store');
        Route::get('/{customer}', [CustomerController::class, 'show'])->name('show');
        Route::get('/{customer}/edit', [CustomerController::class, 'edit'])->name('edit');
        Route::put('/{customer}', [CustomerController::class, 'update'])->name('update');
        Route::post('/{customer}/notes', [CustomerController::class, 'storeNote'])->name('notes.store');
        Route::get('/search', [CustomerSearchController::class, 'search'])->name('search');
        Route::post('/quick-create', [CustomerSearchController::class, 'quickCreate'])->name('quick-create');
        Route::get('/exchange-rates', [CustomerController::class, 'getExchangeRates'])->name('exchange-rates');
    });

    Route::prefix('counters')->name('counters.')->group(function () {
        Route::get('/', [CounterController::class, 'index'])->name('index');
        Route::get('/{counter}/open', [CounterController::class, 'showOpen'])->name('open');
        Route::post('/{counter}/open', [CounterController::class, 'open'])->name('open.store');
        Route::get('/{counter}/close', [CounterController::class, 'showClose'])->name('close.show');
        Route::post('/{counter}/close', [CounterController::class, 'close'])->name('close');
        Route::get('/{counter}/status', [CounterController::class, 'status'])->name('status');
        Route::get('/{counter}/history', [CounterController::class, 'history'])->name('history');
        Route::get('/{counter}/handover', [CounterController::class, 'showHandover'])->name('handover.show');
        Route::post('/{counter}/handover', [CounterController::class, 'handover'])->name('handover');
        Route::get('/{counter}/handover/acknowledge', [CounterController::class, 'showAcknowledgeHandover'])->name('handover.acknowledge.show');
        Route::post('/{counter}/handover/acknowledge', [CounterController::class, 'acknowledgeHandover'])->name('handover.acknowledge');

        Route::get('/{counter}/emergency', [CounterController::class, 'showEmergency'])->name('emergency');
        Route::post('/{counter}/emergency', [CounterController::class, 'emergency'])->name('emergency.store');
        Route::post('/{counter}/emergency-close', [CounterController::class, 'emergency'])->name('emergency-close');
        Route::get('/{counter}/emergency-closure/{closure}', [CounterController::class, 'showEmergencyClosure'])
            ->name('emergency-closure');
    });

    Route::prefix('stock-cash')->name('stock-cash.')->group(function () {
        Route::get('/', [StockCashController::class, 'index'])->name('index');
        Route::get('/position/{position}', [StockCashController::class, 'showPosition'])->name('position')
            ->middleware('role:manager');
        Route::get('/till-report', [StockCashController::class, 'tillReport'])->name('till-report')
            ->middleware('role:manager');
        Route::get('/reconciliation', [StockCashController::class, 'reconciliationReport'])->name('reconciliation');
    });

    Route::prefix('stock-transfers')->name('stock-transfers.')->group(function () {
        Route::get('/', [StockTransferController::class, 'index'])->name('index');
        Route::get('/create', [StockTransferController::class, 'create'])->name('create')
            ->middleware('role:manager');
        Route::post('/', [StockTransferController::class, 'store'])->name('store');
        Route::get('/{stockTransfer}', [StockTransferController::class, 'show'])->name('show');

        Route::get('/{stockTransfer}/dispatch', [StockTransferController::class, 'show'])->name('dispatch.show')
            ->middleware('role:admin');
        Route::get('/{stockTransfer}/receive', [StockTransferController::class, 'show'])->name('receive.show')
            ->middleware('role:admin');
        Route::get('/{stockTransfer}/approve-bm', [StockTransferController::class, 'show'])->name('approve-bm.show')
            ->middleware('role:manager');
        Route::get('/{stockTransfer}/approve-hq', [StockTransferController::class, 'show'])->name('approve-hq.show')
            ->middleware('role:admin');
        Route::get('/{stockTransfer}/cancel', [StockTransferController::class, 'show'])->name('cancel.show')
            ->middleware('role:manager');
        Route::get('/{stockTransfer}/complete', [StockTransferController::class, 'show'])->name('complete.show')
            ->middleware('role:admin');

        Route::post('/{stockTransfer}/approve-bm', [StockTransferController::class, 'approveBm'])->name('approve-bm')
            ->middleware('role:manager');
        Route::post('/{stockTransfer}/approve-hq', [StockTransferController::class, 'approveHq'])->name('approve-hq')
            ->middleware('role:admin');
        Route::post('/{stockTransfer}/dispatch', [StockTransferController::class, 'dispatch'])->name('dispatch')
            ->middleware('role:admin');
        Route::post('/{stockTransfer}/receive', [StockTransferController::class, 'receive'])->name('receive')
            ->middleware('role:admin');
        Route::post('/{stockTransfer}/complete', [StockTransferController::class, 'complete'])->name('complete')
            ->middleware('role:admin');
        Route::post('/{stockTransfer}/cancel', [StockTransferController::class, 'cancel'])->name('cancel')
            ->middleware('role:manager');
    });

    Route::middleware('role:compliance')->group(function () {
        Route::get('/compliance', [DashboardController::class, 'compliance'])->name('compliance');
        Route::get('/compliance/flagged', [DashboardController::class, 'compliance'])->name('compliance.flagged');
        Route::patch('/compliance/flags/{flaggedTransaction}/assign', [DashboardController::class, 'assignFlag'])->name('compliance.flags.assign');
        Route::patch('/compliance/flags/{flaggedTransaction}/resolve', [DashboardController::class, 'resolveFlag'])->name('compliance.flags.resolve');

        Route::prefix('compliance/alerts')->name('compliance.alerts.')->group(function () {
            Route::get('/', [AlertTriageController::class, 'index'])->name('index');
            Route::get('/{alert}', [AlertTriageController::class, 'show'])->name('show');
            Route::post('/{alert}/assign', [AlertTriageController::class, 'assign'])->name('assign');
            Route::post('/{alert}/resolve', [AlertTriageController::class, 'resolve'])->name('resolve');
            Route::post('/{alert}/dismiss', [AlertTriageController::class, 'dismiss'])->name('dismiss');
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
            Route::post('/{case}/documents', [CaseManagementController::class, 'uploadDocument'])->name('documents.upload');
            Route::post('/{case}/documents/{document}/verify', [CaseManagementController::class, 'verifyDocument'])->name('documents.verify');
            Route::post('/{case}/links', [CaseManagementController::class, 'addLink'])->name('links.add');
            Route::delete('/{case}/links/{link}', [CaseManagementController::class, 'removeLink'])->name('links.remove');
        });

        Route::prefix('compliance/risk-dashboard')->name('compliance.risk-dashboard.')->group(function () {
            Route::get('/', [RiskDashboardController::class, 'index'])->name('index');
            Route::get('/customer/{customer}', [RiskDashboardController::class, 'customer'])->name('customer');
            Route::get('/trends', [RiskDashboardController::class, 'trends'])->name('trends');
            Route::post('/rescreen', [RiskDashboardController::class, 'rescreen'])->name('rescreen');
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
            Route::post('/{list}/import', [SanctionListController::class, 'triggerImport'])->name('import');
        });

        Route::prefix('compliance/screening')->name('compliance.screening.')->group(function () {
            Route::get('/{customerId}', [ScreeningController::class, 'show'])->name('show');
            Route::post('/{customerId}', [ScreeningController::class, 'screen'])->name('screen');
        });

        Route::prefix('compliance/findings')->name('compliance.findings.')->group(function () {
            Route::get('/', [FindingController::class, 'index'])->name('index');
            Route::get('/{id}', [FindingController::class, 'show'])->name('show');
            Route::post('/{id}/dismiss', [FindingController::class, 'dismiss'])->name('dismiss');
        });

        // Removed STR/compliance reporting routes (P0 cleanup)
    });

    Route::middleware('role:manager')->prefix('accounting')->name('accounting.')->group(function () {
        Route::get('/', [JournalController::class, 'index'])->name('index');

        // Journal Entry Management
        Route::get('/journal', [JournalController::class, 'index'])->name('journal');
        Route::get('/journal/create', [JournalController::class, 'create'])->name('journal.create');
        Route::post('/journal', [JournalController::class, 'store'])->name('journal.store');
        Route::get('/journal/{entry}', [JournalController::class, 'show'])->name('journal.show');
        Route::post('/journal/{entry}/reverse', [JournalController::class, 'reverse'])->name('journal.reverse');

        // Ledger & Reports
        Route::get('/ledger', [ReportController::class, 'ledger'])->name('ledger');
        Route::get('/ledger/{accountCode}', [ReportController::class, 'ledgerAccount'])->name('ledger.account');

        Route::get('/trial-balance', [ReportController::class, 'trialBalance'])->name('trial-balance');
        Route::get('/profit-loss', [ReportController::class, 'profitLoss'])->name('profit-loss');
        Route::get('/balance-sheet', [ReportController::class, 'balanceSheet'])->name('balance-sheet');
        Route::get('/cash-flow', [ReportController::class, 'cashFlow'])->name('cash-flow');
        Route::get('/ratios', [ReportController::class, 'ratios'])->name('ratios');

        // Periods & Fiscal Years (remain in AccountingController for now)
        Route::get('/periods', [AccountingController::class, 'periods'])->name('periods');
        Route::post('/periods/{period}/close', [AccountingController::class, 'closePeriod'])->name('period.close');
        Route::get('/fiscal-years', [AccountingController::class, 'fiscalYears'])->name('fiscal-years');
        Route::post('/fiscal-years', [FiscalYearController::class, 'store'])->name('fiscal-years.store');
        Route::post('/fiscal-years/{year}/close', [FiscalYearController::class, 'close'])->name('fiscal-years.close');
        Route::get('/revaluation', [AccountingController::class, 'revaluation'])->name('revaluation');
        Route::get('/revaluation/history', [AccountingController::class, 'revaluationHistory'])->name('revaluation.history');
        Route::post('/revaluation/run', [RevaluationController::class, 'run'])->name('revaluation.run');

        // Bank Reconciliation
        Route::get('/reconciliation', [ReconciliationController::class, 'index'])->name('reconciliation');
        Route::get('/reconciliation/report', [ReconciliationController::class, 'reconciliationReport'])->name('reconciliation.report');
        Route::post('/reconciliation/import', [ReconciliationController::class, 'importBankStatement'])->name('reconciliation.import');
        Route::post('/reconciliation/{reconciliation}/exception', [ReconciliationController::class, 'markAsException'])->name('reconciliation.exception');
        Route::get('/reconciliation/export', [ReconciliationController::class, 'exportReconciliation'])->name('reconciliation.export');

        // Budget
        Route::get('/budget', [BudgetController::class, 'index'])->name('budget');
        Route::post('/budget', [BudgetController::class, 'store'])->name('budget.store');
        Route::put('/budget/{budget}', [BudgetController::class, 'update'])->name('budget.update');
        Route::patch('/budget/{budget}', [BudgetController::class, 'update'])->name('budget.patch');
    });

    Route::middleware('role:manager,admin')->prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [DashboardController::class, 'reports'])->name('index');

        Route::get('/msb2', [RegulatoryReportController::class, 'msb2'])->name('msb2');
        Route::post('/msb2/export', [RegulatoryReportController::class, 'msb2Generate'])->name('msb2.export');
        Route::get('/lmca', [RegulatoryReportController::class, 'lmca'])->name('lmca');
        Route::post('/lmca/export', [RegulatoryReportController::class, 'lmcaGenerate'])->name('lmca.export');
        Route::get('/quarterly-lvr', [RegulatoryReportController::class, 'quarterlyLvr'])->name('quarterly-lvr');
        Route::post('/quarterly-lvr/export', [RegulatoryReportController::class, 'quarterlyLvrGenerate'])
            ->name('quarterly-lvr.export');
        Route::get('/position-limit', [RegulatoryReportController::class, 'positionLimit'])->name('position-limit');
        Route::post('/position-limit/export', [RegulatoryReportController::class, 'positionLimitGenerate'])
            ->name('position-limit.export');

        Route::get('/monthly-trends', [AnalyticsController::class, 'monthlyTrends'])->name('monthly-trends');
        Route::get('/profitability', [AnalyticsController::class, 'profitability'])->name('profitability');
        Route::get('/customer-analysis', [AnalyticsController::class, 'customerAnalysis'])->name('customer-analysis');
        Route::get('/compliance-summary', [AnalyticsController::class, 'complianceSummary'])->name('compliance-summary');
    });

    Route::middleware(['role:admin', 'mfa.verified'])->prefix('users')->name('users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('/create', [UserController::class, 'create'])->name('create');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::get('/{user}', [UserController::class, 'show'])->name('show');
        Route::get('/{user}/edit', [UserController::class, 'edit'])->name('edit');
        Route::put('/{user}', [UserController::class, 'update'])->name('update');
        Route::post('/{user}/reset-password', [UserController::class, 'resetPassword'])->name('reset-password');
    });

    Route::middleware(['role:admin'])->prefix('branches')->name('branches.')->group(function () {
        // Branch Closing Workflow
        Route::get('/{branch}/closing', [BranchClosingController::class, 'show'])
            ->name('closing.show');
        Route::post('/{branch}/closing/initiate', [BranchClosingController::class, 'initiate'])
            ->name('closing.initiate');
        Route::post('/{branch}/closing/settle', [BranchClosingController::class, 'settle'])
            ->name('closing.settle');
        Route::post('/{branch}/closing/finalize', [BranchClosingController::class, 'finalize'])
            ->name('closing.finalize');
    });

    Route::middleware(['role:admin'])->prefix('test-results')->name('test-results.')->group(function () {
        Route::get('/compare', [TestResultsController::class, 'compare'])->name('compare');
        Route::get('/', [TestResultsController::class, 'index'])->name('index');
        Route::get('/statistics', [TestResultsController::class, 'statistics'])->name('statistics');
        Route::get('/status', [TestResultsController::class, 'latestStatus'])->name('status');
        Route::post('/run', [TestResultsController::class, 'run'])->name('run');
        Route::get('/{testResult}', [TestResultsController::class, 'show'])->name('show');
        Route::post('/cleanup', [TestResultsController::class, 'cleanup'])->name('cleanup');
        Route::get('/{testResult}/output', [TestResultsController::class, 'output'])->name('output');
    });

});

require __DIR__.'/auth.php';
