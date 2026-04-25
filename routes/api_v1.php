<?php

use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\BulkImportController;
use App\Http\Controllers\Api\V1\Compliance\AlertController;
use App\Http\Controllers\Api\V1\Compliance\CaseController;
use App\Http\Controllers\Api\V1\Compliance\CtosReportController;
use App\Http\Controllers\Api\V1\Compliance\DashboardController;
use App\Http\Controllers\Api\V1\Compliance\EddController;
use App\Http\Controllers\Api\V1\Compliance\FindingController;
use App\Http\Controllers\Api\V1\Compliance\RiskController;
use App\Http\Controllers\Api\V1\CounterOpeningController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\EodReconciliationController;
use App\Http\Controllers\Api\V1\RateController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SanctionController;
use App\Http\Controllers\Api\V1\SanctionListController;
use App\Http\Controllers\Api\V1\ScreeningController;
use App\Http\Controllers\Api\V1\StrController;
use App\Http\Controllers\Api\V1\TellerAllocationController;
use App\Http\Controllers\Api\V1\TransactionApprovalController;
use App\Http\Controllers\Api\V1\TransactionCancellationController;
use App\Http\Controllers\Api\V1\TransactionController;
use App\Http\Controllers\Report\RegulatoryReportController;
use Illuminate\Http\Request;
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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->group(function () {
    // Transactions API
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::post('/transactions', [TransactionController::class, 'store'])
        ->middleware('mfa.verified'); // MFA required for transaction creation (BNM compliance)
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show']);
    Route::post('/transactions/{transaction}/approve', [TransactionApprovalController::class, 'approve'])
        ->middleware(['role:manager', 'mfa.verified']);
    Route::post('/transactions/{transaction}/request-cancellation', [TransactionCancellationController::class, 'requestCancellation'])
        ->middleware(['role:manager', 'mfa.verified']);
    Route::post('/transactions/{transaction}/approve-cancellation', [TransactionCancellationController::class, 'approveCancellation'])
        ->middleware(['role:manager,compliance', 'mfa.verified']);
    Route::post('/transactions/{transaction}/reject-cancellation', [TransactionCancellationController::class, 'rejectCancellation'])
        ->middleware(['role:manager,compliance', 'mfa.verified']);

    // Customers API
    Route::get('/customers', [CustomerController::class, 'index']);
    Route::post('/customers', [CustomerController::class, 'store'])
        ->middleware('throttle:30,1'); // 30 requests per minute
    Route::get('/customers/{customer}', [CustomerController::class, 'show']);
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])
        ->middleware('throttle:30,1');
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])
        ->middleware('throttle:15,1'); // Stricter limit for destructive operation
    Route::get('/customers/{customer}/history', [CustomerController::class, 'customerHistory']);
    Route::post('/customers/{customer}/kyc', [CustomerController::class, 'uploadDocument'])
        ->middleware('throttle:30,1');

    // STR API - Compliance Officer only
    Route::get('/str', [StrController::class, 'index'])
        ->middleware('role:compliance');
    Route::post('/str', [StrController::class, 'store'])
        ->middleware('role:compliance');
    Route::get('/str/{str}', [StrController::class, 'show'])
        ->middleware('role:compliance');
    Route::post('/str/{str}/submit', [StrController::class, 'submit'])
        ->middleware('role:compliance');

    // Sanctions API - Admin only for upload
    Route::post('/sanctions/search', [SanctionController::class, 'search']);
    Route::post('/sanctions/upload', [SanctionController::class, 'upload'])
        ->middleware('role:admin');

    // Reports API
    Route::post('/reports/lctr', [RegulatoryReportController::class, 'generateLCTR'])
        ->name('api.v1.reports.lctr');
    Route::post('/reports/lctr/status', [RegulatoryReportController::class, 'updateLCTRStatus'])
        ->name('api.v1.reports.lctr.status');
    Route::post('/reports/msb2', [RegulatoryReportController::class, 'generateMSB2'])
        ->name('api.v1.reports.msb2');
    Route::post('/reports/msb2/status', [RegulatoryReportController::class, 'updateMSB2Status'])
        ->name('api.v1.reports.msb2.status');
    Route::get('/reports/download/{filename}', [ReportController::class, 'download']);

    // Compliance Findings API
    Route::prefix('compliance')->group(function () {
        Route::get('/findings', [FindingController::class, 'index']);
        Route::get('/findings/stats', [FindingController::class, 'stats']);
        Route::get('/findings/{id}', [FindingController::class, 'show']);
        Route::post('/findings/{id}/dismiss', [FindingController::class, 'dismiss']);

        // Alerts API
        Route::get('/alerts', [AlertController::class, 'index'])
            ->middleware('role:compliance');
        Route::get('/alerts/summary', [AlertController::class, 'summary'])
            ->middleware('role:compliance');
        Route::get('/alerts/overdue', [AlertController::class, 'overdue'])
            ->middleware('role:compliance');
        Route::post('/alerts/bulk-assign', [AlertController::class, 'bulkAssign'])
            ->middleware('role:compliance');
        Route::post('/alerts/bulk-resolve', [AlertController::class, 'bulkResolve'])
            ->middleware('role:compliance');
        Route::post('/alerts/auto-assign', [AlertController::class, 'autoAssign'])
            ->middleware('role:compliance');
        Route::get('/alerts/{id}', [AlertController::class, 'show'])
            ->middleware('role:compliance');

        // Cases API
        Route::get('/cases', [CaseController::class, 'index'])
            ->middleware('role:compliance');
        Route::post('/cases', [CaseController::class, 'store']);
        Route::get('/cases/{id}', [CaseController::class, 'show'])
            ->middleware('role:compliance');
        Route::patch('/cases/{id}', [CaseController::class, 'update']);
        Route::post('/cases/{id}/notes', [CaseController::class, 'addNote']);
        Route::post('/cases/{id}/close', [CaseController::class, 'close']);
        Route::post('/cases/{id}/escalate', [CaseController::class, 'escalate']);
        Route::get('/cases/{id}/timeline', [CaseController::class, 'timeline']);

        // EDD API - Compliance Officer for management
        Route::get('/edd', [EddController::class, 'index'])
            ->middleware('role:compliance');
        Route::get('/edd/templates', [EddController::class, 'templates']);
        Route::get('/edd/{id}', [EddController::class, 'show'])
            ->middleware('role:compliance');
        Route::post('/edd/{id}/questionnaire', [EddController::class, 'submitQuestionnaire'])
            ->middleware('role:compliance');
        Route::post('/edd/{id}/approve', [EddController::class, 'approve'])
            ->middleware('role:compliance');
        Route::post('/edd/{id}/reject', [EddController::class, 'reject'])
            ->middleware('role:compliance');

        // CTOS API - Compliance Officer only
        Route::get('/ctos', [CtosReportController::class, 'index'])
            ->middleware('role:compliance');
        Route::get('/ctos/{id}', [CtosReportController::class, 'show'])
            ->middleware('role:compliance');
        Route::post('/ctos/{id}/submit', [CtosReportController::class, 'submit'])
            ->middleware(['role:compliance', 'mfa.verified']);

        // Dashboard API
        Route::get('/dashboard', [DashboardController::class, 'kpis']);
        Route::get('/calendar', [DashboardController::class, 'calendar']);
        Route::get('/case-aging', [DashboardController::class, 'caseAging']);
        Route::get('/audit-trail', [DashboardController::class, 'auditTrail']);
        Route::get('/audit-trail/export', [DashboardController::class, 'auditTrailExport']);
        Route::get('/reports/auto', [DashboardController::class, 'autoReports']);
    });

    // Risk API
    Route::get('/risk/portfolio', [RiskController::class, 'portfolio']);
    Route::get('/risk/{customerId}', [RiskController::class, 'show']);
    Route::get('/risk/{customerId}/history', [RiskController::class, 'history']);
    Route::post('/risk/{customerId}/recalculate', [RiskController::class, 'recalculate']);
    Route::post('/risk/{customerId}/lock', [RiskController::class, 'lock']);
    Route::post('/risk/{customerId}/unlock', [RiskController::class, 'unlock']);

    // EOD Reconciliation API - Manager or Compliance Officer
    Route::prefix('eod')->group(function () {
        Route::get('/reconciliation/{date}', [EodReconciliationController::class, 'show'])
            ->middleware('role:manager,compliance');
        Route::get('/reconciliation/{date}/counters/{counterId}', [EodReconciliationController::class, 'counterReconciliation'])
            ->middleware('role:manager,compliance');
        Route::get('/reconciliation/{date}/report', [EodReconciliationController::class, 'report'])
            ->middleware('role:manager,compliance');
    });

    // Branches API (Admin only for index, store, update, destroy)
    // show, counters, users accessible to admin OR user's own branch
    Route::middleware(['role:admin'])->group(function () {
        Route::get('branches', [BranchController::class, 'index']);
        Route::post('branches', [BranchController::class, 'store']);
        Route::put('branches/{id}', [BranchController::class, 'update']);
        Route::delete('branches/{id}', [BranchController::class, 'destroy']);
    });

    // Branch routes accessible to all authenticated users (with own branch check in controller)
    Route::get('branches/{id}', [BranchController::class, 'show']);
    Route::get('branches/{id}/counters', [BranchController::class, 'counters']);
    Route::get('branches/{id}/users', [BranchController::class, 'users']);

    // Bulk Import API - Admin or Manager only
    Route::middleware(['role:admin,manager'])->group(function () {
        Route::post('import/customers', [BulkImportController::class, 'importCustomers']);
        Route::post('import/transactions', [BulkImportController::class, 'importTransactions']);
        Route::get('import/status/{jobId}', [BulkImportController::class, 'getStatus']);
        Route::get('import/errors/{jobId}', [BulkImportController::class, 'getErrors']);
    });

    // Sanctions management endpoints (Admin)
    Route::middleware(['role:admin'])->group(function () {
        Route::get('/sanctions/lists', [SanctionListController::class, 'lists']);
        Route::get('/sanctions/entries', [SanctionListController::class, 'entries']);
        Route::post('/sanctions/import/trigger/{list}', [SanctionListController::class, 'triggerImport']);
        Route::get('/sanctions/import/logs', [SanctionListController::class, 'importLogs']);
        Route::post('/sanctions/entries', [SanctionListController::class, 'storeEntry']);
        Route::put('/sanctions/entries/{entry}', [SanctionListController::class, 'updateEntry']);
        Route::delete('/sanctions/entries/{entry}', [SanctionListController::class, 'deleteEntry']);
    });

    // Screening endpoints (ComplianceOfficer+)
    Route::middleware(['role:compliance'])->group(function () {
        Route::post('/screening/customer/{customer}', [ScreeningController::class, 'screen']);
        Route::get('/screening/customer/{customer}/history', [ScreeningController::class, 'history']);
        Route::get('/screening/customer/{customer}/status', [ScreeningController::class, 'status']);
        Route::post('/screening/batch', [ScreeningController::class, 'batchScreen']);
    });

    // Exchange Rates API - Manager/Admin only for modifications
    Route::prefix('rates')->group(function () {
        Route::get('/', [RateController::class, 'index']);
        Route::get('/summary', [RateController::class, 'summary']);
        Route::get('/dates', [RateController::class, 'availableDates']);
        Route::get('/history/{currencyCode}', [RateController::class, 'history']);
        Route::get('/check', [RateController::class, 'checkSet']);
        Route::get('/{currencyCode}', [RateController::class, 'show']);
        Route::post('/fetch', [RateController::class, 'fetchFromApi'])
            ->middleware('role:manager,admin');
        Route::post('/copy-previous', [RateController::class, 'copyPrevious'])
            ->middleware('role:manager,admin');
        Route::put('/{currencyCode}', [RateController::class, 'override'])
            ->middleware('role:manager,admin');
        Route::post('/validate', [RateController::class, 'validateRate']);
    });

    // Teller Allocation API - Part of daily opening workflow
    Route::prefix('allocations')->group(function () {
        // Teller: Get own active allocation
        Route::get('/my-active', [TellerAllocationController::class, 'myActiveAllocation']);
        // Manager: Get pending allocations for their branch
        Route::get('/pending', [TellerAllocationController::class, 'pendingForBranch'])
            ->middleware('role:manager,admin');
        // Manager: Get active allocations for their branch
        Route::get('/active', [TellerAllocationController::class, 'activeForBranch'])
            ->middleware('role:manager,admin');
        // Manager: Approve allocation
        Route::post('/{allocationId}/approve', [TellerAllocationController::class, 'approve'])
            ->middleware('role:manager,admin');
        // Manager: Reject allocation
        Route::post('/{allocationId}/reject', [TellerAllocationController::class, 'reject'])
            ->middleware('role:manager,admin');
        // Manager: Modify active allocation
        Route::post('/{allocationId}/modify', [TellerAllocationController::class, 'modify'])
            ->middleware('role:manager,admin');
        // Manager: Return allocation to pool (EOD)
        Route::post('/{allocationId}/return-to-pool', [TellerAllocationController::class, 'returnToPool'])
            ->middleware('role:manager,admin');
        // Get specific allocation details
        Route::get('/{allocationId}', [TellerAllocationController::class, 'show']);
    });

    // Counter Opening Workflow API - Daily branch opening
    Route::prefix('counters')->group(function () {
        // Manager: Get pending opening requests for branch
        Route::get('/pending-requests', [CounterOpeningController::class, 'pendingRequests'])
            ->middleware('role:manager,admin');
        // Teller: Initiate opening request (request float allocation)
        Route::post('/{counterId}/opening-request', [CounterOpeningController::class, 'initiateOpeningRequest']);
        // Manager: Approve allocations and open counter
        Route::post('/{counterId}/approve-and-open', [CounterOpeningController::class, 'approveAndOpen'])
            ->middleware('role:manager,admin');
    });
});
