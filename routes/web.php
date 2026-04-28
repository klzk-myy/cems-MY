<?php

use App\Http\Controllers\AccountingController;
use App\Http\Controllers\AmlRuleController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\BranchOpeningController;
use App\Http\Controllers\Compliance\AlertTriageController;
use App\Http\Controllers\Compliance\CaseManagementController;
use App\Http\Controllers\Compliance\ComplianceReportingController;
use App\Http\Controllers\Compliance\ComplianceWorkspaceController;
use App\Http\Controllers\Compliance\CtosController;
use App\Http\Controllers\Compliance\EddTemplateController;
use App\Http\Controllers\Compliance\FindingController;
use App\Http\Controllers\Compliance\RiskDashboardController;
use App\Http\Controllers\Compliance\SanctionListController;
use App\Http\Controllers\Compliance\ScreeningController;
use App\Http\Controllers\Compliance\UnifiedAlertController;
use App\Http\Controllers\CounterController;
use App\Http\Controllers\Customer\CustomerKycController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnhancedDiligenceController;
use App\Http\Controllers\FinancialStatementController;
use App\Http\Controllers\FiscalYearController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\LedgerController;
use App\Http\Controllers\MfaController;
use App\Http\Controllers\RateController;
use App\Http\Controllers\Report\AnalyticsController;
use App\Http\Controllers\Report\RegulatoryReportController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RevaluationController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\StockCashController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\StrController;
use App\Http\Controllers\TestQueryLogController;
use App\Http\Controllers\TestResultsController;
use App\Http\Controllers\Transaction\TransactionApprovalController;
use App\Http\Controllers\Transaction\TransactionCancellationController;
use App\Http\Controllers\TransactionBatchController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransactionReportController;
use App\Http\Controllers\UserController;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CEMS-MY Web Routes
|--------------------------------------------------------------------------
|
| Organized by function and features:
| - Operations: Dashboard, Transactions, Customers, Counters, Stock/Cash
| - Compliance & AML: Compliance, STR, EDD, AML Rules
| - Accounting & Finance: Journal, Ledger, Statements, Periods, etc.
| - Reports: BNM Compliance Reports
| - System: Tasks, Audit, Users
|
*/

// =============================================================================
// PUBLIC ROUTES
// =============================================================================

Route::get('/', function () {
    // Check if system is set up
    $isSetupComplete = User::exists() &&
                       Currency::exists() &&
                       ExchangeRate::exists() &&
                       Branch::exists();

    if (! $isSetupComplete) {
        return redirect('/setup');
    }

    if (auth()->check()) {
        return redirect('/dashboard');
    }

    return redirect('/login');
})->name('home');

// Health check endpoint (public, no auth required)
Route::get('/health', [HealthCheckController::class, 'index'])->name('health');

// Test route for query logging
Route::get('/test/query-log', [TestQueryLogController::class, 'index']);

// =============================================================================
// SETUP ROUTES (Public - No auth required)
// =============================================================================

Route::prefix('setup')->name('setup.')->group(function () {
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

// =============================================================================
// AUTHENTICATED ROUTES
// =============================================================================

Route::middleware(['auth', 'session.timeout'])->group(function () {

    // -------------------------------------------------------------------------
    // OPERATIONS - Daily operational tasks
    // -------------------------------------------------------------------------

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // MFA Setup & Verification
    Route::prefix('mfa')->name('mfa.')->group(function () {
        Route::get('/setup', [MfaController::class, 'setup'])->name('setup');
        Route::post('/setup', [MfaController::class, 'setupStore'])->name('setup.store');
        Route::get('/verify', [MfaController::class, 'verify'])->name('verify');
        Route::post('/verify', [MfaController::class, 'verifyStore'])->name('verify.store');
        Route::post('/disable', [MfaController::class, 'disable'])->name('disable');
        Route::get('/recovery', [MfaController::class, 'recovery'])->name('recovery');
        Route::get('/trusted-devices', [MfaController::class, 'trustedDevices'])->name('trusted-devices');
        Route::delete('/trusted-devices/{deviceId}', [MfaController::class, 'removeDevice'])->name('trusted-devices.remove');
    });

    // Exchange Rates (Manager/Admin only)
    Route::middleware('role:manager,admin')->group(function () {
        Route::get('/rates', [RateController::class, 'index'])->name('rates.index');
    });

    // Transactions
    Route::prefix('transactions')->name('transactions.')->group(function () {
        Route::get('/', [TransactionController::class, 'index'])->name('index');
        Route::get('/list', [TransactionController::class, 'index'])->name('list'); // backward compat

        // Create requires MFA
        Route::get('/create', [TransactionController::class, 'create'])->name('create')
            ->middleware('mfa.verified');
        Route::post('/', [TransactionController::class, 'store'])->name('store')
            ->middleware(['mfa.verified', 'throttle:transactions']);

        // Batch upload (Manager only) - must be before /{transaction} wildcard
        Route::middleware('role:manager')->group(function () {
            Route::get('/batch-upload', [TransactionBatchController::class, 'showBatchUpload'])->name('batch-upload');
            Route::post('/batch-upload', [TransactionBatchController::class, 'processBatchUpload']);
            Route::get('/import/{import}', [TransactionBatchController::class, 'showImportResults'])->name('batch-upload.show');
            Route::get('/template', [TransactionBatchController::class, 'downloadTemplate'])->name('template');
        });

        // View
        Route::get('/{transaction}', [TransactionController::class, 'show'])->name('show');
        Route::get('/{transaction}/receipt', [TransactionController::class, 'receipt'])->name('receipt');

        // Approval & Cancellation
        Route::post('/{transaction}/approve', [TransactionApprovalController::class, 'approve'])->name('approve')
            ->middleware(['role:manager', 'mfa.verified']);
        Route::get('/{transaction}/cancel', [TransactionCancellationController::class, 'showCancel'])->name('cancel.show')
            ->middleware('mfa.verified');
        Route::post('/{transaction}/cancel', [TransactionCancellationController::class, 'cancel'])->name('cancel')
            ->middleware(['role:manager', 'mfa.verified']);

        // Large transaction confirmation
        Route::get('/{transaction}/confirm', [TransactionApprovalController::class, 'showConfirm'])->name('confirm.show')
            ->middleware('role:manager');
        Route::post('/{transaction}/confirm', [TransactionApprovalController::class, 'confirm'])->name('confirm')
            ->middleware('role:manager');
    });

    // Customer Transaction History (API)
    Route::get('/customers/{customer}/history', [TransactionReportController::class, 'customerHistory'])
        ->name('customers.history');
    Route::get('/customers/{customer}/history/export', [TransactionReportController::class, 'exportCustomerHistory'])
        ->name('customers.export');

    // Customers
    Route::prefix('customers')->name('customers.')->group(function () {
        Route::get('/search', [CustomerController::class, 'search'])->name('search');
        Route::post('/quick-create', [CustomerController::class, 'quickCreate'])->name('quickCreate')
            ->middleware('throttle:10,1');
        Route::get('/exchange-rates', [CustomerController::class, 'getExchangeRates'])->name('exchangeRates');
        Route::get('/', [CustomerController::class, 'index'])->name('index');
        Route::get('/create', [CustomerController::class, 'create'])->name('create');
        Route::post('/', [CustomerController::class, 'store'])->name('store')
            ->middleware('throttle:30,1');
        Route::get('/{customer}', [CustomerController::class, 'show'])->name('show');
        Route::get('/{customer}/edit', [CustomerController::class, 'edit'])->name('edit');
        Route::put('/{customer}', [CustomerController::class, 'update'])->name('update')
            ->middleware('throttle:30,1');
        Route::delete('/{customer}', [CustomerController::class, 'destroy'])->name('destroy')
            ->middleware('throttle:15,1');

        // KYC Document Management
        Route::get('/{customer}/kyc', [CustomerKycController::class, 'kyc'])->name('kyc');
        Route::post('/{customer}/kyc', [CustomerKycController::class, 'uploadDocument'])->name('kyc.upload')
            ->middleware('throttle:30,1');
        Route::post('/{customer}/kyc/{document}/verify', [CustomerKycController::class, 'verifyDocument'])->name('kyc.verify')
            ->middleware('role:compliance');
        Route::delete('/{customer}/kyc/{document}', [CustomerKycController::class, 'deleteDocument'])->name('kyc.delete')
            ->middleware('throttle:15,1');
    });

    // Counters
    Route::prefix('counters')->name('counters.')->group(function () {
        Route::get('/', [CounterController::class, 'index'])->name('index');
        Route::get('/{counter}/open', [CounterController::class, 'showOpen'])->name('open.show');
        Route::post('/{counter}/open', [CounterController::class, 'open'])->name('open');
        Route::get('/{counter}/close', [CounterController::class, 'showClose'])->name('close.show');
        Route::post('/{counter}/close', [CounterController::class, 'close'])->name('close');
        Route::get('/{counter}/status', [CounterController::class, 'status'])->name('status');
        Route::get('/{counter}/history', [CounterController::class, 'history'])->name('history');
        Route::get('/{counter}/handover', [CounterController::class, 'showHandover'])->name('handover.show');
        Route::post('/{counter}/handover', [CounterController::class, 'handover'])->name('handover');
    });

    // Stock & Cash
    Route::prefix('stock-cash')->name('stock-cash.')->group(function () {
        Route::get('/', [StockCashController::class, 'index'])->name('index');
        Route::post('/open', [StockCashController::class, 'openTill'])->name('open')
            ->middleware('role:manager');
        Route::post('/close', [StockCashController::class, 'closeTill'])->name('close')
            ->middleware('role:manager');
        Route::get('/position/{position}', [StockCashController::class, 'showPosition'])->name('position')
            ->middleware('role:manager');
        Route::get('/till-report', [StockCashController::class, 'tillReport'])->name('till-report')
            ->middleware('role:manager');
        Route::get('/reconciliation', [StockCashController::class, 'reconciliationReport'])->name('reconciliation');
    });

    // Stock Transfers
    Route::prefix('stock-transfers')->name('stock-transfers.')->group(function () {
        Route::get('/', [StockTransferController::class, 'index'])->name('index');
        Route::get('/create', [StockTransferController::class, 'create'])->name('create')
            ->middleware('role:manager');
        Route::post('/', [StockTransferController::class, 'store'])->name('store')
            ->middleware('role:manager');
        Route::get('/{stockTransfer}', [StockTransferController::class, 'show'])->name('show');
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

    // -------------------------------------------------------------------------
    // COMPLIANCE & AML - BNM regulatory compliance
    // -------------------------------------------------------------------------

    // Compliance Dashboard (Compliance Officers only)
    Route::middleware('role:compliance')->group(function () {
        Route::get('/compliance', [DashboardController::class, 'compliance'])->name('compliance');
        Route::get('/compliance/flagged', [DashboardController::class, 'compliance'])->name('compliance.flagged');
        Route::patch('/compliance/flags/{flaggedTransaction}/assign', [DashboardController::class, 'assignFlag'])->name('compliance.flags.assign');
        Route::patch('/compliance/flags/{flaggedTransaction}/resolve', [DashboardController::class, 'resolveFlag'])->name('compliance.flags.resolve');

        // Generate STR from alert
        Route::post('/compliance/flags/{flaggedTransaction}/generate-str', [StrController::class, 'generateFromAlert'])->name('compliance.flags.generate-str');

        // Compliance Workspace
        Route::get('/compliance/workspace', [ComplianceWorkspaceController::class, 'index'])->name('compliance.workspace');

        // Alert Triage
        Route::prefix('compliance/alerts')->name('compliance.alerts.')->group(function () {
            Route::get('/', [AlertTriageController::class, 'index'])->name('index');
            Route::get('/{alert}', [AlertTriageController::class, 'show'])->name('show');
            Route::post('/{alert}/assign', [AlertTriageController::class, 'assign'])->name('assign');
            Route::post('/{alert}/resolve', [AlertTriageController::class, 'resolve'])->name('resolve');
            Route::post('/{alert}/dismiss', [AlertTriageController::class, 'dismiss'])->name('dismiss');
        });

        // Unified Alerts
        Route::get('/compliance/unified', [UnifiedAlertController::class, 'index'])
            ->name('compliance.unified.index');

        // Case Management
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

        // Risk Dashboard
        Route::prefix('compliance/risk-dashboard')->name('compliance.risk-dashboard.')->group(function () {
            Route::get('/', [RiskDashboardController::class, 'index'])->name('index');
            Route::get('/customer/{customer}', [RiskDashboardController::class, 'customer'])->name('customer');
            Route::get('/trends', [RiskDashboardController::class, 'trends'])->name('trends');
            Route::post('/rescreen', [RiskDashboardController::class, 'rescreen'])->name('rescreen');
        });

        // Sanction Lists
        Route::prefix('compliance/sanctions')->name('compliance.sanctions.')->group(function () {
            Route::get('/', [SanctionListController::class, 'index'])->name('index');
            Route::get('/{list}', [SanctionListController::class, 'show'])->name('show');
            Route::post('/{list}/import', [SanctionListController::class, 'triggerImport'])->name('import');
            Route::get('/entries', [SanctionListController::class, 'entriesIndex'])->name('entries.index');
            Route::get('/entries/create', [SanctionListController::class, 'createEntry'])->name('entries.create');
            Route::post('/entries', [SanctionListController::class, 'storeEntry'])->name('entries.store');
            Route::get('/entries/{entry}', [SanctionListController::class, 'showEntry'])->name('entries.show');
            Route::get('/entries/{entry}/edit', [SanctionListController::class, 'editEntry'])->name('entries.edit');
            Route::put('/entries/{entry}', [SanctionListController::class, 'updateEntry'])->name('entries.update');
            Route::get('/import-logs', [SanctionListController::class, 'importLogs'])->name('import-logs');
        });

        // CTOS Reports
        Route::prefix('compliance/ctos')->name('compliance.ctos.')->group(function () {
            Route::get('/', [CtosController::class, 'index'])->name('index');
            Route::get('/{id}', [CtosController::class, 'show'])->name('show');
            Route::post('/{id}/submit', [CtosController::class, 'submit'])->name('submit');
        });

        // Customer Screening
        Route::prefix('compliance/screening')->name('compliance.screening.')->group(function () {
            Route::get('/{customerId}', [ScreeningController::class, 'show'])->name('show');
            Route::post('/{customerId}', [ScreeningController::class, 'screen'])->name('screen');
        });

        // Compliance Findings
        Route::prefix('compliance/findings')->name('compliance.findings.')->group(function () {
            Route::get('/', [FindingController::class, 'index'])->name('index');
            Route::get('/{id}', [FindingController::class, 'show'])->name('show');
            Route::post('/{id}/dismiss', [FindingController::class, 'dismiss'])->name('dismiss');
        });

        // EDD Templates
        Route::prefix('compliance/edd-templates')->name('compliance.edd-templates.')->group(function () {
            Route::get('/', [EddTemplateController::class, 'index'])->name('index');
            Route::post('/', [EddTemplateController::class, 'store'])->name('store');
            Route::get('/{template}', [EddTemplateController::class, 'show'])->name('show');
            Route::put('/{template}', [EddTemplateController::class, 'update'])->name('update');
            Route::delete('/{template}', [EddTemplateController::class, 'destroy'])->name('destroy');
            Route::post('/{template}/duplicate', [EddTemplateController::class, 'duplicate'])->name('duplicate');
        });

        // Compliance Reporting
        Route::prefix('compliance/reporting')->name('compliance.reporting.')->group(function () {
            Route::get('/', [ComplianceReportingController::class, 'index'])->name('index');
            Route::get('/generate', [ComplianceReportingController::class, 'generate'])->name('generate');
            Route::post('/run', [ComplianceReportingController::class, 'run'])->name('run');
            Route::get('/history', [ComplianceReportingController::class, 'history'])->name('history');
            Route::get('/history/{id}/download', [ComplianceReportingController::class, 'download'])->name('history.download');
            Route::get('/schedule', [ComplianceReportingController::class, 'schedule'])->name('schedule');
            Route::post('/schedule', [ComplianceReportingController::class, 'createSchedule'])->name('schedule.create');
            Route::patch('/schedule/{id}', [ComplianceReportingController::class, 'updateSchedule'])->name('schedule.update');
            Route::delete('/schedule/{id}', [ComplianceReportingController::class, 'deleteSchedule'])->name('schedule.delete');
            Route::get('/deadlines', [ComplianceReportingController::class, 'deadlines'])->name('deadlines');
        });
    });

    // AML Rules (Compliance Officers only)
    Route::middleware('role:compliance')->prefix('compliance/rules')->name('compliance.rules.')->group(function () {
        Route::get('/', [AmlRuleController::class, 'index'])->name('index');
        Route::get('/create', [AmlRuleController::class, 'create'])->name('create');
        Route::post('/', [AmlRuleController::class, 'store'])->name('store');
        Route::get('/{rule}', [AmlRuleController::class, 'show'])->name('show');
        Route::get('/{rule}/edit', [AmlRuleController::class, 'edit'])->name('edit');
        Route::put('/{rule}', [AmlRuleController::class, 'update'])->name('update');
        Route::patch('/{rule}/toggle', [AmlRuleController::class, 'toggle'])->name('toggle');
        Route::delete('/{rule}', [AmlRuleController::class, 'destroy'])->name('destroy');
    });

    // STR Reports - Compliance officers create/manage
    Route::middleware('role:compliance')->prefix('str')->name('str.')->group(function () {
        Route::get('/', [StrController::class, 'index'])->name('index');
        Route::get('/create', [StrController::class, 'create'])->name('create');
        Route::post('/', [StrController::class, 'store'])->name('store')
            ->middleware('throttle:str-submission');
        Route::get('/{str}', [StrController::class, 'show'])->name('show');
        Route::get('/{str}/edit', [StrController::class, 'edit'])->name('edit');
        Route::put('/{str}', [StrController::class, 'update'])->name('update');
        Route::post('/{str}/submit-review', [StrController::class, 'submitForReview'])->name('submit-review');
        Route::post('/{str}/submit-approval', [StrController::class, 'submitForApproval'])->name('submit-approval');
        Route::post('/{str}/track-acknowledgment', [StrController::class, 'trackAcknowledgment'])->name('track-acknowledgment');
    });

    // STR Approval - Managers only (segregation of duties)
    Route::middleware('role:manager')->prefix('str')->name('str.')->group(function () {
        Route::post('/{str}/approve', [StrController::class, 'approve'])->name('approve');
        // Managers submit to goAML after approval
        Route::post('/{str}/submit', [StrController::class, 'submit'])->name('submit')
            ->middleware(['mfa.verified', 'throttle:str-submission']);
    });

    // Enhanced Due Diligence - Compliance officers create/manage
    Route::middleware('role:compliance')->prefix('compliance/edd')->name('compliance.edd.')->group(function () {
        Route::get('/', [EnhancedDiligenceController::class, 'index'])->name('index');
        Route::get('/create', [EnhancedDiligenceController::class, 'create'])->name('create');
        Route::post('/', [EnhancedDiligenceController::class, 'store'])->name('store');
        Route::get('/{record}', [EnhancedDiligenceController::class, 'show'])->name('show');
        Route::get('/{record}/edit', [EnhancedDiligenceController::class, 'edit'])->name('edit');
        Route::put('/{record}', [EnhancedDiligenceController::class, 'update'])->name('update');
        Route::post('/{record}/submit', [EnhancedDiligenceController::class, 'submitReview'])->name('submit');
    });

    // EDD Approval - Managers and Compliance officers
    Route::middleware('role:manager,compliance')->prefix('compliance/edd')->name('compliance.edd.')->group(function () {
        Route::post('/{record}/approve', [EnhancedDiligenceController::class, 'approve'])->name('approve');
        Route::post('/{record}/reject', [EnhancedDiligenceController::class, 'reject'])->name('reject');
    });

    // -------------------------------------------------------------------------
    // ACCOUNTING & FINANCE - Double-entry accounting
    // -------------------------------------------------------------------------

    Route::middleware('role:manager')->prefix('accounting')->name('accounting.')->group(function () {
        Route::get('/', [DashboardController::class, 'accounting'])->name('index');
        Route::get('/journal', [AccountingController::class, 'index'])->name('journal');
        Route::get('/journal/create', [AccountingController::class, 'create'])->name('journal.create');
        Route::post('/journal', [AccountingController::class, 'store'])->name('journal.store');
        Route::get('/journal/{entry}', [AccountingController::class, 'show'])->name('journal.show');
        Route::post('/journal/{entry}/reverse', [AccountingController::class, 'reverse'])->name('journal.reverse');

        Route::get('/ledger', [LedgerController::class, 'index'])->name('ledger');
        Route::get('/ledger/{accountCode}', [LedgerController::class, 'account'])->name('ledger.account');
        Route::get('/trial-balance', [FinancialStatementController::class, 'trialBalance'])->name('trial-balance');
        Route::get('/profit-loss', [FinancialStatementController::class, 'profitLoss'])->name('profit-loss');
        Route::get('/balance-sheet', [FinancialStatementController::class, 'balanceSheet'])->name('balance-sheet');
        Route::get('/cash-flow', [FinancialStatementController::class, 'cashFlow'])->name('cash-flow');
        Route::get('/ratios', [FinancialStatementController::class, 'ratios'])->name('ratios');
        Route::get('/periods', [AccountingController::class, 'periods'])->name('periods');
        Route::post('/periods/{period}/close', [AccountingController::class, 'closePeriod'])->name('period.close');
        Route::get('/fiscal-years', [FiscalYearController::class, 'index'])->name('fiscal-years');
        Route::post('/fiscal-years', [FiscalYearController::class, 'store'])->name('fiscal-years.store');
        Route::post('/fiscal-years/{year}/close', [FiscalYearController::class, 'close'])->name('fiscal-years.close');
        Route::get('/fiscal-years/{yearCode}/report', [FiscalYearController::class, 'report'])->name('fiscal-years.report');
        Route::get('/revaluation', [RevaluationController::class, 'index'])->name('revaluation');
        Route::post('/revaluation/run', [RevaluationController::class, 'run'])->name('revaluation.run');
        Route::get('/revaluation/history', [RevaluationController::class, 'history'])->name('revaluation.history');
        Route::get('/reconciliation', [AccountingController::class, 'reconciliation'])->name('reconciliation');
        Route::post('/reconciliation/import', [AccountingController::class, 'importBankStatement'])->name('reconciliation.import');
        Route::post('/reconciliation/{reconciliation}/exception', [AccountingController::class, 'markAsException'])->name('reconciliation.exception');
        Route::get('/reconciliation/report', [AccountingController::class, 'reconciliationReport'])->name('reconciliation.report');
        Route::get('/reconciliation/export', [AccountingController::class, 'exportReconciliation'])->name('reconciliation.export');
        Route::get('/budget', [AccountingController::class, 'budget'])->name('budget');
        Route::post('/budget', [AccountingController::class, 'storeBudget'])->name('budget.store');
        Route::put('/budget/{budget}', [AccountingController::class, 'updateBudget'])->name('budget.update');
        Route::patch('/budget/{budget}', [AccountingController::class, 'updateBudget'])->name('budget.patch');
    });

    // -------------------------------------------------------------------------
    // REPORTS - BNM Compliance Reporting
    // -------------------------------------------------------------------------

    Route::middleware('role:manager,admin')->prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [DashboardController::class, 'reports'])->name('index');

        // BNM Regulatory Reports
        Route::get('/msb2', [RegulatoryReportController::class, 'msb2'])->name('msb2');
        Route::get('/msb2/generate', [RegulatoryReportController::class, 'msb2Generate'])->name('msb2.generate');
        Route::get('/lctr', [RegulatoryReportController::class, 'lctr'])->name('lctr');
        Route::get('/lctr/generate', [RegulatoryReportController::class, 'lctrGenerate'])->name('lctr.generate');
        Route::get('/lmca', [RegulatoryReportController::class, 'lmca'])->name('lmca');
        Route::get('/lmca/generate', [RegulatoryReportController::class, 'lmcaGenerate'])->name('lmca.generate');
        Route::get('/quarterly-lvr', [RegulatoryReportController::class, 'quarterlyLvr'])->name('quarterly-lvr');
        Route::get('/quarterly-lvr/generate', [RegulatoryReportController::class, 'quarterlyLvrGenerate'])->name('quarterly-lvr.generate');
        Route::get('/position-limit', [RegulatoryReportController::class, 'positionLimit'])->name('position-limit');
        Route::get('/position-limit/generate', [RegulatoryReportController::class, 'positionLimitGenerate'])->name('position-limit.generate');

        // Analytics
        Route::get('/monthly-trends', [AnalyticsController::class, 'monthlyTrends'])->name('monthly-trends');
        Route::get('/profitability', [AnalyticsController::class, 'profitability'])->name('profitability');
        Route::get('/customer-analysis', [AnalyticsController::class, 'customerAnalysis'])->name('customer-analysis');
        Route::get('/compliance-summary', [AnalyticsController::class, 'complianceSummary'])->name('compliance-summary');

        // Report Management
        Route::get('/history', [ReportController::class, 'history'])->name('history');
        Route::get('/download/{filename}', [ReportController::class, 'download'])->name('download');
        Route::get('/compare', [ReportController::class, 'compare'])->name('compare');
        Route::get('/export', [ReportController::class, 'export'])->name('export');
    });

    // -------------------------------------------------------------------------
    // SYSTEM - Administrative
    // -------------------------------------------------------------------------

    // Audit Log (Managers only)
    Route::middleware(['role:manager'])->prefix('audit')->name('audit.')->group(function () {
        Route::get('/', [AuditController::class, 'index'])->name('index');
        Route::get('/dashboard', [AuditController::class, 'dashboard'])->name('dashboard');
        Route::get('/rotate', [AuditController::class, 'rotate'])->name('rotate');
        Route::get('/{log}', [AuditController::class, 'show'])->name('show');
        Route::post('/export', [AuditController::class, 'export'])->name('export');
    });

    // User Management (Admin only)
    Route::middleware(['role:admin', 'mfa.verified'])->prefix('users')->name('users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('/create', [UserController::class, 'create'])->name('create');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::get('/{user}', [UserController::class, 'show'])->name('show');
        Route::get('/{user}/edit', [UserController::class, 'edit'])->name('edit');
        Route::put('/{user}', [UserController::class, 'update'])->name('update');
        Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
        Route::post('/{user}/toggle', [UserController::class, 'toggleActive'])->name('toggle');
        Route::post('/{user}/reset-password', [UserController::class, 'resetPassword'])->name('reset-password');
    });

    // Branch Management (Admin only)
    Route::middleware(['auth', 'role:admin'])->prefix('branches')->name('branches.')->group(function () {
        Route::get('/', [BranchController::class, 'index'])->name('index');
        Route::get('/create', [BranchController::class, 'create'])->name('create');
        Route::post('/', [BranchController::class, 'store'])->name('store');
        Route::get('/{branch}', [BranchController::class, 'show'])->name('show');
        Route::get('/{branch}/edit', [BranchController::class, 'edit'])->name('edit');
        Route::put('/{branch}', [BranchController::class, 'update'])->name('update');
        Route::delete('/{branch}', [BranchController::class, 'destroy'])->name('destroy');
    });

    // Branch Opening Wizard (Admin only)
    Route::middleware(['auth', 'role:admin'])->prefix('branches/open')->name('branches.open.')->group(function () {
        Route::get('/', [BranchOpeningController::class, 'index'])->name('index');
        Route::get('/step1', [BranchOpeningController::class, 'step1'])->name('step1');
        Route::post('/step1', [BranchOpeningController::class, 'processStep1'])->name('step1.process');
        Route::get('/step2/{branch}', [BranchOpeningController::class, 'step2'])->name('step2');
        Route::post('/step2/{branch}', [BranchOpeningController::class, 'processStep2'])->name('step2.process');
        Route::get('/step3/{branch}', [BranchOpeningController::class, 'step3'])->name('step3');
        Route::post('/step3/{branch}', [BranchOpeningController::class, 'processStep3'])->name('step3.process');
        Route::get('/complete/{branch}', [BranchOpeningController::class, 'complete'])->name('complete');
    });

    // -------------------------------------------------------------------------
    // API ROUTES
    // -------------------------------------------------------------------------

    // Exchange Rate History
    Route::get('/api/rates/history/{currency}', [DashboardController::class, 'rateHistory'])
        ->name('api.rates.history');

    // Test Results (Admin only)
    Route::middleware(['role:admin'])->prefix('test-results')->name('test-results.')->group(function () {
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
