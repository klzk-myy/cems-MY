<?php

use App\Http\Controllers\Api\V1\BranchClosingController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\Compliance\AlertController;
use App\Http\Controllers\Api\V1\Compliance\CaseController;
use App\Http\Controllers\Api\V1\Compliance\DashboardController;
use App\Http\Controllers\Api\V1\Compliance\EddController;
use App\Http\Controllers\Api\V1\Compliance\FindingController;
use App\Http\Controllers\Api\V1\Compliance\RiskController;
use App\Http\Controllers\Api\V1\CounterApiController;
use App\Http\Controllers\Api\V1\CounterHandoverController;
use App\Http\Controllers\Api\V1\CounterOpeningController;
use App\Http\Controllers\Api\V1\CurrentUserController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\EmergencyCounterController;
use App\Http\Controllers\Api\V1\EodReconciliationController;
use App\Http\Controllers\Api\V1\MonthEndCloseController;
use App\Http\Controllers\Api\V1\RateController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SanctionController;
use App\Http\Controllers\Api\V1\SanctionListController;
use App\Http\Controllers\Api\V1\ScreeningController;
use App\Http\Controllers\Api\V1\TellerAllocationController;
use App\Http\Controllers\Api\V1\TransactionApprovalController;
use App\Http\Controllers\Api\V1\TransactionCancellationController;
use App\Http\Controllers\Api\V1\TransactionController;
use App\Http\Controllers\Report\RegulatoryReportController;
use App\Http\Controllers\TransactionWizardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 Routes
|--------------------------------------------------------------------------
|
| API version 1 routes for the CEMS-MY Currency Exchange Management System.
| These routes are prefixed with 'api/v1' and use the same middleware
| as the original API routes.
|
*/

Route::get('/user', CurrentUserController::class)->name('api.v1.user');

Route::middleware(['branch.scope'])->group(function () {
    // Transactions API
    Route::get('/transactions', [TransactionController::class, 'index'])
        ->name('api.v1.transactions.index');
    Route::post('/transactions', [TransactionController::class, 'store'])
        ->middleware('mfa.verified') // MFA required for transaction creation (BNM compliance)
        ->name('api.v1.transactions.store');
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])
        ->name('api.v1.transactions.show');
    Route::post('/transactions/{transaction}/approve', [TransactionApprovalController::class, 'approve'])
        ->middleware(['role:manager', 'mfa.verified'])
        ->name('api.v1.transactions.approve');
    Route::post('/transactions/{transaction}/request-cancellation', [TransactionCancellationController::class, 'requestCancellation'])
        ->middleware(['role:manager', 'mfa.verified'])
        ->name('api.v1.transactions.request-cancellation');
    Route::post('/transactions/{transaction}/approve-cancellation', [TransactionCancellationController::class, 'approveCancellation'])
        ->middleware(['role:manager,compliance', 'mfa.verified'])
        ->name('api.v1.transactions.approve-cancellation');
    Route::post('/transactions/{transaction}/reject-cancellation', [TransactionCancellationController::class, 'rejectCancellation'])
        ->middleware(['role:manager,compliance', 'mfa.verified'])
        ->name('api.v1.transactions.reject-cancellation');

    // Transaction Wizard API
    Route::prefix('wizard/transactions')->middleware('role:teller')->group(function () {
        Route::post('/step1', [TransactionWizardController::class, 'step1'])
            ->name('api.v1.wizard.transactions.step1');
        Route::post('/step2', [TransactionWizardController::class, 'step2'])
            ->name('api.v1.wizard.transactions.step2');
        Route::post('/step3', [TransactionWizardController::class, 'step3'])
            ->name('api.v1.wizard.transactions.step3');
        Route::get('/{sessionId}/status', [TransactionWizardController::class, 'status'])
            ->name('api.v1.wizard.transactions.status');
        Route::delete('/{sessionId}', [TransactionWizardController::class, 'cancel'])
            ->name('api.v1.wizard.transactions.cancel');
    });

    // Customers API
    Route::get('/customers', [CustomerController::class, 'index'])
        ->name('api.v1.customers.index');
    Route::get('/customers/search', [CustomerController::class, 'searchForTransaction'])
        ->name('api.v1.customers.search');
    Route::post('/customers', [CustomerController::class, 'store'])
        ->middleware('throttle:30,1') // 30 requests per minute
        ->name('api.v1.customers.store');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])
        ->name('api.v1.customers.show');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])
        ->middleware('throttle:30,1')
        ->name('api.v1.customers.update');
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])
        ->middleware('throttle:15,1') // Stricter limit for destructive operation
        ->name('api.v1.customers.destroy');
    Route::get('/customers/{customer}/history', [CustomerController::class, 'customerHistory'])
        ->name('api.v1.customers.history');
    Route::post('/customers/{customer}/kyc', [CustomerController::class, 'uploadDocument'])
        ->middleware('throttle:30,1')
        ->name('api.v1.customers.kyc');

    // Sanctions API - Admin only for upload
    Route::post('/sanctions/search', [SanctionController::class, 'search'])
        ->name('api.v1.sanctions.search');
    Route::post('/sanctions/upload', [SanctionController::class, 'upload'])
        ->middleware('role:admin')
        ->name('api.v1.sanctions.upload');

    // Reports API
    Route::post('/reports/msb2', [RegulatoryReportController::class, 'generateMSB2'])
        ->name('api.v1.reports.msb2');
    Route::post('/reports/msb2/status', [RegulatoryReportController::class, 'updateMSB2Status'])
        ->name('api.v1.reports.msb2.status');
    Route::get('/reports/download/{filename}', [ReportController::class, 'download'])
        ->name('api.v1.reports.download');

    // Compliance Findings API
    Route::prefix('compliance')->group(function () {
        Route::get('/findings', [FindingController::class, 'index'])
            ->name('api.v1.compliance.findings.index');
        Route::get('/findings/stats', [FindingController::class, 'stats'])
            ->name('api.v1.compliance.findings.stats');
        Route::get('/findings/{id}', [FindingController::class, 'show'])
            ->name('api.v1.compliance.findings.show');
        Route::post('/findings/{id}/dismiss', [FindingController::class, 'dismiss'])
            ->name('api.v1.compliance.findings.dismiss');

        // Alerts API
        Route::get('/alerts', [AlertController::class, 'index'])
            ->middleware('role:compliance')
            ->name('api.v1.compliance.alerts.index');
        Route::get('/alerts/summary', [AlertController::class, 'summary'])
            ->middleware('role:compliance')
            ->name('api.v1.compliance.alerts.summary');
        Route::get('/alerts/overdue', [AlertController::class, 'overdue'])
            ->middleware('role:compliance')
            ->name('api.v1.compliance.alerts.overdue');
        Route::post('/alerts/bulk-assign', [AlertController::class, 'bulkAssign'])
            ->middleware('role:compliance')
            ->name('api.v1.compliance.alerts.bulk-assign');
        Route::post('/alerts/bulk-resolve', [AlertController::class, 'bulkResolve'])
            ->middleware('role:compliance')
            ->name('api.v1.compliance.alerts.bulk-resolve');
        Route::post('/alerts/auto-assign', [AlertController::class, 'autoAssign'])
            ->middleware('role:compliance')
            ->name('api.v1.compliance.alerts.auto-assign');
        Route::get('/alerts/{id}', [AlertController::class, 'show'])
            ->middleware('role:compliance')
            ->name('api.v1.compliance.alerts.show');

        // Cases API
        Route::get('/cases', [CaseController::class, 'index'])
            ->middleware('role:compliance')
            ->name('api.v1.compliance.cases.index');
        Route::post('/cases', [CaseController::class, 'store'])
            ->middleware('role:compliance')
            ->name('api.v1.compliance.cases.store');
        Route::get('/cases/{id}', [CaseController::class, 'show'])
            ->middleware('role:compliance')
            ->name('api.v1.compliance.cases.show');
        Route::patch('/cases/{id}', [CaseController::class, 'update'])
            ->middleware('role:compliance')
            ->name('api.v1.compliance.cases.update');
        Route::post('/cases/{id}/notes', [CaseController::class, 'addNote'])
            ->middleware('role:compliance')
            ->name('api.v1.compliance.cases.notes');
        Route::post('/cases/{id}/close', [CaseController::class, 'close'])
            ->middleware('role:compliance')
            ->name('api.v1.compliance.cases.close');
        Route::post('/cases/{id}/escalate', [CaseController::class, 'escalate'])
            ->middleware('role:compliance')
            ->name('api.v1.compliance.cases.escalate');
        Route::get('/cases/{id}/timeline', [CaseController::class, 'timeline'])
            ->middleware('role:compliance')
            ->name('api.v1.compliance.cases.timeline');

        // EDD API - Compliance Officer for management
        Route::get('/edd', [EddController::class, 'index'])
            ->middleware('role:compliance')
            ->name('api.v1.compliance.edd.index');
        Route::get('/edd/templates', [EddController::class, 'templates'])
            ->middleware('role:compliance')
            ->name('api.v1.compliance.edd.templates');
        Route::get('/edd/{id}', [EddController::class, 'show'])
            ->middleware('role:compliance')
            ->name('api.v1.compliance.edd.show');
        Route::post('/edd/{id}/questionnaire', [EddController::class, 'submitQuestionnaire'])
            ->middleware('role:compliance')
            ->name('api.v1.compliance.edd.questionnaire');
        Route::post('/edd/{id}/approve', [EddController::class, 'approve'])
            ->middleware('role:compliance')
            ->name('api.v1.compliance.edd.approve');
        Route::post('/edd/{id}/reject', [EddController::class, 'reject'])
            ->middleware('role:compliance')
            ->name('api.v1.compliance.edd.reject');

        // Dashboard API
        Route::get('/dashboard', [DashboardController::class, 'kpis'])
            ->name('api.v1.compliance.dashboard.kpis');
        Route::get('/calendar', [DashboardController::class, 'calendar'])
            ->name('api.v1.compliance.dashboard.calendar');
        Route::get('/case-aging', [DashboardController::class, 'caseAging'])
            ->name('api.v1.compliance.dashboard.case-aging');
        Route::get('/audit-trail', [DashboardController::class, 'auditTrail'])
            ->name('api.v1.compliance.dashboard.audit-trail');
        Route::get('/audit-trail/export', [DashboardController::class, 'auditTrailExport'])
            ->name('api.v1.compliance.dashboard.audit-trail.export');
        Route::get('/reports/auto', [DashboardController::class, 'autoReports'])
            ->name('api.v1.compliance.dashboard.auto-reports');
    });

    // Risk API
    Route::get('/risk/portfolio', [RiskController::class, 'portfolio'])
        ->name('api.v1.risk.portfolio');
    Route::get('/risk/{customerId}', [RiskController::class, 'show'])
        ->name('api.v1.risk.show');
    Route::get('/risk/{customerId}/history', [RiskController::class, 'history'])
        ->name('api.v1.risk.history');
    Route::post('/risk/{customerId}/recalculate', [RiskController::class, 'recalculate'])
        ->name('api.v1.risk.recalculate');
    Route::post('/risk/{customerId}/lock', [RiskController::class, 'lock'])
        ->name('api.v1.risk.lock');
    Route::post('/risk/{customerId}/unlock', [RiskController::class, 'unlock'])
        ->name('api.v1.risk.unlock');

    // EOD Reconciliation API - Manager or Compliance Officer
    Route::prefix('eod')->group(function () {
        Route::get('/reconciliation/{date}', [EodReconciliationController::class, 'show'])
            ->middleware('role:manager,compliance')
            ->name('api.v1.eod.reconciliation.show');
        Route::get('/reconciliation/{date}/counters/{counterId}', [EodReconciliationController::class, 'counterReconciliation'])
            ->middleware('role:manager,compliance')
            ->name('api.v1.eod.reconciliation.counter');
        Route::get('/reconciliation/{date}/report', [EodReconciliationController::class, 'report'])
            ->middleware('role:manager,compliance')
            ->name('api.v1.eod.reconciliation.report');
    });

    // Branches API (Admin only for index, store, update, destroy)
    // show, counters, users accessible to admin OR user's own branch
    Route::middleware(['role:admin'])->group(function () {
        Route::get('branches', [BranchController::class, 'index'])
            ->name('api.v1.branches.index');
        Route::post('branches', [BranchController::class, 'store'])
            ->name('api.v1.branches.store');
        Route::put('branches/{id}', [BranchController::class, 'update'])
            ->name('api.v1.branches.update');
        Route::delete('branches/{id}', [BranchController::class, 'destroy'])
            ->name('api.v1.branches.destroy');
    });

    // Branch routes accessible to all authenticated users (with own branch check in controller)
    Route::get('branches/{id}', [BranchController::class, 'show'])
        ->name('api.v1.branches.show');
    Route::get('branches/{id}/counters', [BranchController::class, 'counters'])
        ->name('api.v1.branches.counters');
    Route::get('branches/{id}/users', [BranchController::class, 'users'])
        ->name('api.v1.branches.users');

    // Sanctions management endpoints (Admin)
    Route::middleware(['role:admin'])->group(function () {
        Route::get('/sanctions/lists', [SanctionListController::class, 'lists'])
            ->name('api.v1.sanctions.lists');
        Route::get('/sanctions/entries', [SanctionListController::class, 'entries'])
            ->name('api.v1.sanctions.entries');
        Route::post('/sanctions/import/trigger/{list}', [SanctionListController::class, 'triggerImport'])
            ->name('api.v1.sanctions.import.trigger');
        Route::get('/sanctions/import/logs', [SanctionListController::class, 'importLogs'])
            ->name('api.v1.sanctions.import.logs');
        Route::post('/sanctions/entries', [SanctionListController::class, 'storeEntry'])
            ->name('api.v1.sanctions.entries.store');
        Route::put('/sanctions/entries/{entry}', [SanctionListController::class, 'updateEntry'])
            ->name('api.v1.sanctions.entries.update');
        Route::delete('/sanctions/entries/{entry}', [SanctionListController::class, 'deleteEntry'])
            ->name('api.v1.sanctions.entries.destroy');
    });

    // Screening endpoints (ComplianceOfficer+)
    Route::middleware(['role:compliance'])->group(function () {
        Route::post('/screening/customer/{customer}', [ScreeningController::class, 'screen'])
            ->name('api.v1.screening.customer');
        Route::get('/screening/customer/{customer}/history', [ScreeningController::class, 'history'])
            ->name('api.v1.screening.customer.history');
        Route::get('/screening/customer/{customer}/status', [ScreeningController::class, 'status'])
            ->name('api.v1.screening.customer.status');
        Route::post('/screening/batch', [ScreeningController::class, 'batchScreen'])
            ->name('api.v1.screening.batch');
    });

    // Exchange Rates API - Manager/Admin only for modifications
    Route::prefix('rates')->group(function () {
        Route::get('/', [RateController::class, 'index'])
            ->name('api.v1.rates.index');
        Route::get('/summary', [RateController::class, 'summary'])
            ->name('api.v1.rates.summary');
        Route::get('/dates', [RateController::class, 'availableDates'])
            ->name('api.v1.rates.dates');
        Route::get('/history/{currencyCode}', [RateController::class, 'history'])
            ->name('api.v1.rates.history');
        Route::get('/check', [RateController::class, 'checkSet'])
            ->name('api.v1.rates.check');
        Route::get('/{currencyCode}', [RateController::class, 'show'])
            ->name('api.v1.rates.show');
        Route::post('/fetch', [RateController::class, 'fetchFromApi'])
            ->middleware('role:manager,admin')
            ->name('api.v1.rates.fetch');
        Route::post('/copy-previous', [RateController::class, 'copyPrevious'])
            ->middleware('role:manager,admin')
            ->name('api.v1.rates.copy-previous');
        Route::put('/{currencyCode}', [RateController::class, 'apiOverride'])
            ->middleware('role:manager,admin')
            ->name('api.v1.rates.override');
        Route::post('/validate', [RateController::class, 'validateRate'])
            ->name('api.v1.rates.validate');
    });

    // Teller Allocation API - Part of daily opening workflow
    Route::prefix('allocations')->group(function () {
        // Teller: Get own active allocation
        Route::get('/my-active', [TellerAllocationController::class, 'myActiveAllocation'])
            ->name('api.v1.allocations.my-active');
        // Manager: Get pending allocations for their branch
        Route::get('/pending', [TellerAllocationController::class, 'pendingForBranch'])
            ->middleware('role:manager,admin')
            ->name('api.v1.allocations.pending');
        // Manager: Get active allocations for their branch
        Route::get('/active', [TellerAllocationController::class, 'activeForBranch'])
            ->middleware('role:manager,admin')
            ->name('api.v1.allocations.active');
        // Manager: Approve allocation
        Route::post('/{allocationId}/approve', [TellerAllocationController::class, 'approve'])
            ->middleware('role:manager,admin')
            ->name('api.v1.allocations.approve');
        // Manager: Reject allocation
        Route::post('/{allocationId}/reject', [TellerAllocationController::class, 'reject'])
            ->middleware('role:manager,admin')
            ->name('api.v1.allocations.reject');
        // Manager: Modify active allocation
        Route::post('/{allocationId}/modify', [TellerAllocationController::class, 'modify'])
            ->middleware('role:manager,admin')
            ->name('api.v1.allocations.modify');
        // Manager: Return allocation to pool (EOD)
        Route::post('/{allocationId}/return-to-pool', [TellerAllocationController::class, 'returnToPool'])
            ->middleware('role:manager,admin')
            ->name('api.v1.allocations.return-to-pool');
        // Get specific allocation details
        Route::get('/{allocationId}', [TellerAllocationController::class, 'show'])
            ->name('api.v1.allocations.show');
    });

    // Counter Opening Workflow API - Daily branch opening
    Route::prefix('counters')->group(function () {
        Route::get('/pending-requests', [CounterOpeningController::class, 'pendingRequests'])
            ->middleware('role:manager,admin')
            ->name('api.v1.counters.pending-requests');
        Route::post('/{counterId}/opening-request', [CounterOpeningController::class, 'initiateOpeningRequest'])
            ->name('api.v1.counters.opening-request');
        Route::post('/{counterId}/approve-and-open', [CounterOpeningController::class, 'approveAndOpen'])
            ->middleware(['role:manager,admin', 'mfa.verified'])
            ->name('api.v1.counters.approve-and-open');

        // Emergency Counter Close
        Route::post('/{counterId}/emergency-close', [EmergencyCounterController::class, 'initiateClose'])
            ->middleware(['role:teller,manager,admin', 'mfa.verified'])
            ->name('api.v1.counters.emergency-close');
        Route::get('/{counterId}/emergency/{closureId}/variance', [EmergencyCounterController::class, 'getVariance'])
            ->middleware(['role:manager,admin', 'mfa.verified'])
            ->name('api.v1.counters.emergency.variance');
        Route::post('/{counterId}/emergency/{closureId}/acknowledge', [EmergencyCounterController::class, 'acknowledge'])
            ->middleware(['role:manager,admin', 'mfa.verified'])
            ->name('api.v1.counters.emergency.acknowledge');

        // Handover Acknowledge
        Route::post('/{counterId}/handover/{handoverId}/acknowledge', [CounterHandoverController::class, 'acknowledge'])
            ->middleware(['role:manager', 'mfa.verified'])
            ->name('api.v1.counters.handover.acknowledge');

        // Counter Close
        Route::post('/{counterId}/close', [CounterApiController::class, 'close'])
            ->middleware(['role:teller,manager,admin', 'mfa.verified'])
            ->name('api.v1.counters.close');
    });

    // Branch Closing Workflow API
    Route::prefix('branches/{branchId}/closing')->middleware('role:manager,admin')->group(function () {
        Route::post('/initiate', [BranchClosingController::class, 'initiate'])
            ->name('api.v1.branches.closing.initiate');
        Route::get('/checklist', [BranchClosingController::class, 'checklist'])
            ->name('api.v1.branches.closing.checklist');
        Route::post('/finalize', [BranchClosingController::class, 'finalize'])
            ->name('api.v1.branches.closing.finalize');
        Route::get('/', [BranchClosingController::class, 'show'])
            ->name('api.v1.branches.closing.show');
    });

    // Month-End Close API - Manager/Admin only
    Route::prefix('accounting/month-end')->middleware('role:manager,admin')->group(function () {
        Route::post('/close', [MonthEndCloseController::class, 'close'])
            ->name('api.v1.accounting.month-end.close');
        Route::get('/status/{date}', [MonthEndCloseController::class, 'status'])
            ->name('api.v1.accounting.month-end.status');
    });
});
