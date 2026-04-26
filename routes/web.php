<?php

use App\Http\Controllers\AccountingController;
use App\Http\Controllers\BranchOpeningController;
use App\Http\Controllers\Compliance\AlertTriageController;
use App\Http\Controllers\Compliance\CaseManagementController;
use App\Http\Controllers\Compliance\ComplianceReportingController;
use App\Http\Controllers\Compliance\ComplianceWorkspaceController;
use App\Http\Controllers\Compliance\CtosController;
use App\Http\Controllers\Compliance\FindingController;
use App\Http\Controllers\Compliance\RiskDashboardController;
use App\Http\Controllers\Compliance\SanctionListController;
use App\Http\Controllers\Compliance\ScreeningController;
use App\Http\Controllers\Compliance\UnifiedAlertController;
use App\Http\Controllers\CounterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FiscalYearController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\MfaController;
use App\Http\Controllers\RevaluationController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\StockCashController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\StrController;
use App\Http\Controllers\TestResultsController;
use App\Http\Controllers\Transaction\TransactionApprovalController;
use App\Http\Controllers\Transaction\TransactionCancellationController;
use App\Http\Controllers\TransactionBatchController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransactionReportController;
use App\Livewire\Accounting\BalanceSheet;
use App\Livewire\Accounting\BudgetReport;
use App\Livewire\Accounting\CashFlow;
use App\Livewire\Accounting\FiscalYears;
use App\Livewire\Accounting\Index as AccountingIndex;
use App\Livewire\Accounting\Journal\Create as JournalCreate;
use App\Livewire\Accounting\Journal\Index as JournalIndex;
use App\Livewire\Accounting\Journal\Show as JournalShow;
use App\Livewire\Accounting\Ledger\Account as LedgerAccount;
use App\Livewire\Accounting\Ledger\Index as LedgerIndex;
use App\Livewire\Accounting\Periods;
use App\Livewire\Accounting\ProfitLoss;
use App\Livewire\Accounting\Ratios;
use App\Livewire\Accounting\Reconciliation\Report;
use App\Livewire\Accounting\Revaluation\History;
use App\Livewire\Accounting\TrialBalance;
use App\Livewire\Audit\Dashboard as AuditDashboard;
use App\Livewire\Audit\Index as AuditIndex;
use App\Livewire\Branches\Create as BranchCreate;
use App\Livewire\Branches\Edit as BranchEdit;
use App\Livewire\Branches\Index as BranchIndex;
use App\Livewire\Branches\Show as BranchShow;
use App\Livewire\Compliance\Alerts\Index as AlertsIndex;
use App\Livewire\Compliance\Alerts\Show as AlertsShow;
use App\Livewire\Compliance\Ctos\Index as CtosIndex;
use App\Livewire\Compliance\Dashboard;
use App\Livewire\Compliance\Edd\Form as EddForm;
use App\Livewire\Compliance\Edd\Index as EddIndex;
use App\Livewire\Compliance\Edd\Templates\Index as EddTemplatesIndex;
use App\Livewire\Compliance\Reporting\Index as ReportingIndex;
use App\Livewire\Compliance\RiskDashboard\Index as RiskDashboardIndex;
use App\Livewire\Compliance\Rules\Form as RulesForm;
use App\Livewire\Compliance\Rules\Index as RulesIndex;
use App\Livewire\Compliance\Sanctions\Index as SanctionsIndex;
use App\Livewire\Compliance\Sanctions\Show as SanctionsShow;
use App\Livewire\Counters\Close;
use App\Livewire\Counters\Handover;
use App\Livewire\Counters\Index;
use App\Livewire\Counters\Open;
use App\Livewire\Customers\Create as CustomerCreate;
use App\Livewire\Customers\Edit as CustomerEdit;
use App\Livewire\Customers\Index as CustomerIndex;
use App\Livewire\Customers\Show as CustomerShow;
use App\Livewire\Rates\Index as RatesIndex;
use App\Livewire\Reports\Analytics\ComplianceSummary;
use App\Livewire\Reports\Analytics\CustomerAnalysis;
use App\Livewire\Reports\Analytics\MonthlyTrends;
use App\Livewire\Reports\Analytics\Profitability;
use App\Livewire\Reports\Compare\Index as CompareIndex;
use App\Livewire\Reports\History\Index as HistoryIndex;
use App\Livewire\Reports\Lctr\Index as LctrIndex;
use App\Livewire\Reports\Lmca\Index as LmcaIndex;
use App\Livewire\Reports\Msb2\Index as Msb2Index;
use App\Livewire\Reports\PositionLimit\Index as PositionLimitIndex;
use App\Livewire\Reports\QuarterlyLvr\Index as QuarterlyLvrIndex;
use App\Livewire\Stock\Index as StockIndex;
use App\Livewire\Stock\Position;
use App\Livewire\Stock\Reconciliation;
use App\Livewire\Stock\TillReport;
use App\Livewire\Stock\Transfer\Create as StockTransferCreate;
use App\Livewire\Stock\Transfer\Index as TransferIndex;
use App\Livewire\Stock\Transfer\Show;
use App\Livewire\Transactions\Approve as TransactionApprove;
use App\Livewire\Transactions\Cancel as TransactionCancel;
use App\Livewire\Transactions\Create as TransactionCreate;
use App\Livewire\Transactions\Index as TransactionIndex;
use App\Livewire\Transactions\Show as TransactionShow;
use App\Livewire\Users\Create as UserCreate;
use App\Livewire\Users\Edit as UserEdit;
use App\Livewire\Users\Index as UserIndex;
use App\Livewire\Users\ResetPassword as UserResetPassword;
use App\Livewire\Users\Show as UserShow;
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
    Route::middleware('auth')->prefix('rates')->name('rates.')->group(function () {
        Route::get('/', RatesIndex::class)->name('index');
    });

    // Transactions
    Route::prefix('transactions')->name('transactions.')->group(function () {
        Route::get('/', TransactionIndex::class)->name('index');
        Route::get('/list', TransactionIndex::class)->name('list'); // backward compat

        // Create requires MFA
        Route::get('/create', TransactionCreate::class)->name('create')
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
        Route::get('/{transaction}', TransactionShow::class)->name('show');
        Route::get('/{transaction}/receipt', [TransactionController::class, 'receipt'])->name('receipt');

        // Approval & Cancellation
        Route::post('/{transaction}/approve', [TransactionApprovalController::class, 'approve'])->name('approve')
            ->middleware(['role:manager', 'mfa.verified']);
        Route::get('/{transaction}/cancel', TransactionCancel::class)->name('cancel.show')
            ->middleware(['role:manager', 'mfa.verified']);
        Route::post('/{transaction}/cancel', [TransactionCancellationController::class, 'cancel'])->name('cancel')
            ->middleware(['role:manager', 'mfa.verified']);

        // Large transaction confirmation
        Route::get('/{transaction}/confirm', TransactionApprove::class)->name('confirm.show')
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
        Route::get('/', CustomerIndex::class)->name('index');
        Route::get('/create', CustomerCreate::class)->name('create');
        Route::get('/{customer}', CustomerShow::class)->name('show');
        Route::get('/{customer}/edit', CustomerEdit::class)->name('edit');
    });

    // Counters
    Route::prefix('counters')->name('counters.')->group(function () {
        Route::get('/', Index::class)->name('index');
        Route::get('/open', Open::class)->name('open');
        Route::post('/open', Open::class)->name('open.store');
        Route::get('/{counter}/close', Close::class)->name('close.show');
        Route::post('/{counter}/close', Close::class)->name('close');
        Route::get('/{counter}/status', [CounterController::class, 'status'])->name('status');
        Route::get('/{counter}/history', [CounterController::class, 'history'])->name('history');
        Route::get('/{counter}/handover', Handover::class)->name('handover.show');
        Route::post('/{counter}/handover', Handover::class)->name('handover');
    });

    // Stock & Cash
    Route::prefix('stock-cash')->name('stock-cash.')->group(function () {
        Route::get('/', StockIndex::class)->name('index');
        Route::post('/open', [StockCashController::class, 'openTill'])->name('open')
            ->middleware('role:manager');
        Route::post('/close', [StockCashController::class, 'closeTill'])->name('close')
            ->middleware('role:manager');
        Route::get('/position/{position}', Position::class)->name('position')
            ->middleware('role:manager');
        Route::get('/till-report', TillReport::class)->name('till-report')
            ->middleware('role:manager');
        Route::get('/reconciliation', Reconciliation::class)->name('reconciliation');
    });

    // Stock Transfers
    Route::prefix('stock-transfers')->name('stock-transfers.')->group(function () {
        // Page views - Livewire components
        Route::get('/', TransferIndex::class)->name('index');
        Route::get('/create', StockTransferCreate::class)->name('create')
            ->middleware('role:manager');
        Route::get('/{stockTransfer}', Show::class)->name('show');

        // Action routes - Controller actions (API-like operations)
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
        Route::get('/compliance', Dashboard::class)->name('compliance');
        Route::get('/compliance/flagged', [DashboardController::class, 'compliance'])->name('compliance.flagged');
        Route::patch('/compliance/flags/{flaggedTransaction}/assign', [DashboardController::class, 'assignFlag'])->name('compliance.flags.assign');
        Route::patch('/compliance/flags/{flaggedTransaction}/resolve', [DashboardController::class, 'resolveFlag'])->name('compliance.flags.resolve');

        // Generate STR from alert
        Route::post('/compliance/flags/{flaggedTransaction}/generate-str', [StrController::class, 'generateFromAlert'])->name('compliance.flags.generate-str');

        // Compliance Workspace
        Route::get('/compliance/workspace', [ComplianceWorkspaceController::class, 'index'])->name('compliance.workspace');

        // Alert Triage
        Route::prefix('compliance/alerts')->name('compliance.alerts.')->group(function () {
            Route::get('/', AlertsIndex::class)->name('index');
            Route::get('/{alert}', AlertsShow::class)->name('show');
            Route::post('/{alert}/assign', [AlertTriageController::class, 'assign'])->name('assign');
            Route::post('/{alert}/resolve', [AlertTriageController::class, 'resolve'])->name('resolve');
            Route::post('/{alert}/dismiss', [AlertTriageController::class, 'dismiss'])->name('dismiss');
        });

        // Unified Alerts
        Route::get('/compliance/unified', [UnifiedAlertController::class, 'index'])
            ->name('compliance.unified.index');

        // Case Management
        Route::prefix('compliance/cases')->name('compliance.cases.')->group(function () {
            Route::get('/', App\Livewire\Compliance\Cases\Index::class)->name('index');
            Route::post('/', [CaseManagementController::class, 'store'])->name('store');
            Route::get('/{case}', App\Livewire\Compliance\Cases\Show::class)->name('show');
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
            Route::get('/', RiskDashboardIndex::class)->name('index');
            Route::get('/customer/{customer}', [RiskDashboardController::class, 'customer'])->name('customer');
            Route::get('/trends', [RiskDashboardController::class, 'trends'])->name('trends');
            Route::post('/rescreen', [RiskDashboardController::class, 'rescreen'])->name('rescreen');
        });

        // Sanction Lists
        Route::prefix('compliance/sanctions')->name('compliance.sanctions.')->group(function () {
            Route::get('/', SanctionsIndex::class)->name('index');
            Route::get('/{list}', SanctionsShow::class)->name('show');
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
            Route::get('/', CtosIndex::class)->name('index');
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

        // Compliance Reporting
        Route::prefix('compliance/reporting')->name('compliance.reporting.')->group(function () {
            Route::get('/', ReportingIndex::class)->name('index');
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
        Route::get('/', RulesIndex::class)->name('index');
        Route::get('/create', RulesForm::class)->name('create');
        Route::get('/{rule}', RulesForm::class)->name('show');
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

    // Enhanced Due Diligence - Livewire components
    Route::middleware('role:compliance')->prefix('compliance/edd')->name('compliance.edd.')->group(function () {
        Route::get('/', EddIndex::class)->name('index');
        Route::get('/create', EddForm::class)->name('create');
        Route::get('/{record}', EddForm::class)->name('show');
    });

    // EDD Templates - Livewire component
    Route::middleware('role:compliance')->prefix('compliance/edd-templates')->name('compliance.edd-templates.')->group(function () {
        Route::get('/', EddTemplatesIndex::class)->name('index');
    });

    // -------------------------------------------------------------------------
    // ACCOUNTING & FINANCE - Double-entry accounting
    // -------------------------------------------------------------------------

    Route::middleware('role:manager')->prefix('accounting')->name('accounting.')->group(function () {
        // Dashboard & Journal - Livewire components
        Route::get('/', AccountingIndex::class)->name('index');
        Route::get('/journal', JournalIndex::class)->name('journal');
        Route::get('/journal/create', JournalCreate::class)->name('journal.create');
        Route::get('/journal/{entry}', JournalShow::class)->name('journal.show');

        // Action routes - Controller actions (POST)
        Route::post('/journal', [AccountingController::class, 'store'])->name('journal.store');
        Route::post('/journal/{entry}/reverse', [AccountingController::class, 'reverse'])->name('journal.reverse');

        Route::get('/ledger', LedgerIndex::class)->name('ledger');
        Route::get('/ledger/{accountCode}', LedgerAccount::class)->name('ledger.account');

        // Financial Reports - Livewire components
        Route::get('/trial-balance', TrialBalance::class)->name('trial-balance');
        Route::get('/profit-loss', ProfitLoss::class)->name('profit-loss');
        Route::get('/balance-sheet', BalanceSheet::class)->name('balance-sheet');
        Route::get('/cash-flow', CashFlow::class)->name('cash-flow');
        Route::get('/ratios', Ratios::class)->name('ratios');

        Route::get('/periods', Periods::class)->name('periods');
        Route::post('/periods/{period}/close', [AccountingController::class, 'closePeriod'])->name('period.close');
        Route::get('/fiscal-years', FiscalYears::class)->name('fiscal-years');
        Route::post('/fiscal-years', [FiscalYearController::class, 'store'])->name('fiscal-years.store');
        Route::post('/fiscal-years/{year}/close', [FiscalYearController::class, 'close'])->name('fiscal-years.close');
        Route::get('/fiscal-years/{yearCode}/report', [FiscalYearController::class, 'report'])->name('fiscal-years.report');
        Route::get('/revaluation', App\Livewire\Accounting\Revaluation\Index::class)->name('revaluation');
        Route::get('/revaluation/history', History::class)->name('revaluation.history');
        Route::post('/revaluation/run', [RevaluationController::class, 'run'])->name('revaluation.run');
        Route::get('/reconciliation', App\Livewire\Accounting\Reconciliation\Index::class)->name('reconciliation');
        Route::get('/reconciliation/report', Report::class)->name('reconciliation.report');
        Route::post('/reconciliation/import', [AccountingController::class, 'importBankStatement'])->name('reconciliation.import');
        Route::post('/reconciliation/{reconciliation}/exception', [AccountingController::class, 'markAsException'])->name('reconciliation.exception');
        Route::get('/reconciliation/export', [AccountingController::class, 'exportReconciliation'])->name('reconciliation.export');
        Route::get('/budget', BudgetReport::class)->name('budget');
        Route::post('/budget', [AccountingController::class, 'storeBudget'])->name('budget.store');
        Route::put('/budget/{budget}', [AccountingController::class, 'updateBudget'])->name('budget.update');
        Route::patch('/budget/{budget}', [AccountingController::class, 'updateBudget'])->name('budget.patch');
    });

    // -------------------------------------------------------------------------
    // REPORTS - BNM Compliance Reporting
    // -------------------------------------------------------------------------

    Route::middleware('role:manager,admin')->prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [DashboardController::class, 'reports'])->name('index');

        // BNM Regulatory Reports - Livewire Components
        Route::get('/msb2', Msb2Index::class)->name('msb2');
        Route::get('/lctr', LctrIndex::class)->name('lctr');
        Route::get('/lmca', LmcaIndex::class)->name('lmca');
        Route::get('/quarterly-lvr', QuarterlyLvrIndex::class)->name('quarterly-lvr');
        Route::get('/position-limit', PositionLimitIndex::class)->name('position-limit');

        // Analytics - Livewire Components
        Route::get('/monthly-trends', MonthlyTrends::class)->name('monthly-trends');
        Route::get('/profitability', Profitability::class)->name('profitability');
        Route::get('/customer-analysis', CustomerAnalysis::class)->name('customer-analysis');
        Route::get('/compliance-summary', ComplianceSummary::class)->name('compliance-summary');

        // Report Management - Livewire Components
        Route::get('/history', HistoryIndex::class)->name('history');
        Route::get('/compare', CompareIndex::class)->name('compare');
    });

    // -------------------------------------------------------------------------
    // SYSTEM - Administrative
    // -------------------------------------------------------------------------

    // Audit Log (Managers only)
    Route::middleware(['auth', 'role:manager'])->prefix('audit')->name('audit.')->group(function () {
        Route::get('/', AuditIndex::class)->name('index');
        Route::get('/dashboard', AuditDashboard::class)->name('dashboard');
    });

    // User Management (Admin only)
    Route::middleware(['auth', 'role:admin', 'mfa.verified'])->prefix('users')->name('users.')->group(function () {
        Route::get('/', UserIndex::class)->name('index');
        Route::get('/create', UserCreate::class)->name('create');
        Route::get('/{user}', UserShow::class)->name('show');
        Route::get('/{user}/edit', UserEdit::class)->name('edit');
        Route::get('/{user}/reset-password', UserResetPassword::class)->name('reset-password');
    });

    // Branch Management (Admin only)
    Route::middleware(['auth', 'role:admin'])->prefix('branches')->name('branches.')->group(function () {
        Route::get('/', BranchIndex::class)->name('index');
        Route::get('/create', BranchCreate::class)->name('create');
        Route::get('/{branch}', BranchShow::class)->name('show');
        Route::get('/{branch}/edit', BranchEdit::class)->name('edit');
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
