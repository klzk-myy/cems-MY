<?php

use App\Http\Controllers\Api\V1\Compliance\AlertController as V1AlertController;
use App\Http\Controllers\Api\V1\Compliance\CaseController as V1CaseController;
use App\Http\Controllers\Api\V1\Compliance\DashboardController as V1DashboardController;
use App\Http\Controllers\Api\V1\Compliance\EddController as V1EddController;
use App\Http\Controllers\Api\V1\Compliance\FindingController as V1FindingController;
use App\Http\Controllers\Api\V1\Compliance\RiskController as V1RiskController;
use App\Http\Controllers\Api\SanctionsWebhookController;
use App\Http\Controllers\Api\V1\CustomerController as V1CustomerController;
use App\Http\Controllers\Api\V1\SanctionListController;
use App\Http\Controllers\Api\V1\ScreeningController;
use App\Http\Controllers\Api\V1\TransactionController as V1TransactionController;
use App\Http\Controllers\Report\RegulatoryReportController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Api\V1\SanctionController;
use App\Http\Controllers\StrController;
use App\Http\Controllers\Transaction\TransactionApprovalController;
use App\Http\Controllers\Transaction\TransactionCancellationController;
use App\Http\Controllers\TransactionWizardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// DEPRECATED: Use api/v1 routes instead. This file will be removed in future versions.

/*
|--------------------------------------------------------------------------
| API Routes (Legacy)
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
| IMPORTANT: This file is deprecated. Please use routes/api_v1.php instead.
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Sanctions Webhook (for external list providers to trigger immediate updates)
// Protected by token-based authentication, not session-based auth
Route::post('/webhooks/sanctions/update', [SanctionsWebhookController::class, 'invoke'])
    ->name('webhooks.sanctions.update');
Route::get('/webhooks/sanctions/health', [SanctionsWebhookController::class, 'health'])
    ->name('webhooks.sanctions.health');

Route::middleware('auth:sanctum')->group(function () {
    // Transactions API (V1 JSON endpoints)
    Route::get('/transactions', [V1TransactionController::class, 'index']);
    Route::post('/transactions', [V1TransactionController::class, 'store']);
    Route::get('/transactions/{transaction}', [V1TransactionController::class, 'show']);
    Route::post('/transactions/{transaction}/approve', [TransactionApprovalController::class, 'approve'])
        ->middleware(['role:manager', 'mfa.verified']);
    Route::post('/transactions/{transaction}/cancel', [TransactionCancellationController::class, 'cancel'])
        ->middleware(['role:manager', 'mfa.verified']);

    // Transaction Wizard API
    Route::prefix('wizard/transactions')->middleware('role:teller')->group(function () {
        Route::post('/step1', [TransactionWizardController::class, 'step1'])
            ->name('api.wizard.transactions.step1');
        Route::post('/step2', [TransactionWizardController::class, 'step2'])
            ->name('api.wizard.transactions.step2');
        Route::post('/step3', [TransactionWizardController::class, 'step3'])
            ->name('api.wizard.transactions.step3');
        Route::get('/{sessionId}/status', [TransactionWizardController::class, 'status'])
            ->name('api.wizard.transactions.status');
        Route::delete('/{sessionId}', [TransactionWizardController::class, 'cancel'])
            ->name('api.wizard.transactions.cancel');
    });

    // Customers API (V1 JSON endpoints)
    Route::get('/customers', [V1CustomerController::class, 'index']);
    Route::post('/customers', [V1CustomerController::class, 'store'])
        ->middleware('throttle:30,1'); // 30 requests per minute
    Route::get('/customers/{customer}', [V1CustomerController::class, 'show']);
    Route::put('/customers/{customer}', [V1CustomerController::class, 'update'])
        ->middleware('throttle:30,1');
    Route::delete('/customers/{customer}', [V1CustomerController::class, 'destroy'])
        ->middleware('throttle:15,1'); // Stricter limit for destructive operation
    Route::get('/customers/{customer}/history', [V1CustomerController::class, 'customerHistory']);
    Route::post('/customers/{customer}/kyc', [V1CustomerController::class, 'uploadDocument'])
        ->middleware('throttle:30,1');

    // STR API
    Route::get('/str', [StrController::class, 'index']);
    Route::post('/str', [StrController::class, 'store']);
    Route::get('/str/{str}', [StrController::class, 'show']);
    Route::post('/str/{str}/submit', [StrController::class, 'submit']);

    // Sanctions API
    Route::post('/sanctions/search', [SanctionController::class, 'search']);
    Route::post('/sanctions/upload', [SanctionController::class, 'upload']);

    // Screening endpoints (ComplianceOfficer+)
    Route::prefix('screening')->middleware(['auth', 'role:compliance'])->group(function () {
        Route::post('/customer/{customer}', [ScreeningController::class, 'screen']);
        Route::get('/customer/{customer}/history', [ScreeningController::class, 'history']);
        Route::get('/customer/{customer}/status', [ScreeningController::class, 'status']);
        Route::post('/batch', [ScreeningController::class, 'batchScreen']);
    });

    // Sanctions management endpoints (Admin)
    Route::prefix('sanctions')->middleware(['auth', 'role:admin'])->group(function () {
        Route::get('/lists', [SanctionListController::class, 'lists']);
        Route::get('/entries', [SanctionListController::class, 'entries']);
        Route::post('/import/trigger/{list}', [SanctionListController::class, 'triggerImport']);
        Route::get('/import/logs', [SanctionListController::class, 'importLogs']);
        Route::post('/entries', [SanctionListController::class, 'storeEntry']);
        Route::put('/entries/{entry}', [SanctionListController::class, 'updateEntry']);
        Route::delete('/entries/{entry}', [SanctionListController::class, 'deleteEntry']);
    });

    // Reports API
    Route::post('/reports/lctr', [RegulatoryReportController::class, 'generateLCTR'])
        ->name('api.reports.lctr');
    Route::post('/reports/lctr/status', [RegulatoryReportController::class, 'updateLCTRStatus'])
        ->name('api.reports.lctr.status');
    Route::post('/reports/msb2', [RegulatoryReportController::class, 'generateMSB2'])
        ->name('api.reports.msb2');
    Route::post('/reports/msb2/status', [RegulatoryReportController::class, 'updateMSB2Status'])
        ->name('api.reports.msb2.status');
    Route::get('/reports/download/{filename}', [ReportController::class, 'download']);

    // Compliance Findings API
    Route::prefix('compliance')->group(function () {
        Route::get('/findings', [V1FindingController::class, 'index']);
        Route::get('/findings/stats', [V1FindingController::class, 'stats']);
        Route::get('/findings/{id}', [V1FindingController::class, 'show']);
        Route::post('/findings/{id}/dismiss', [V1FindingController::class, 'dismiss']);

        // Alerts API
        Route::get('/alerts', [V1AlertController::class, 'index']);
        Route::get('/alerts/summary', [V1AlertController::class, 'summary']);
        Route::get('/alerts/overdue', [V1AlertController::class, 'overdue']);
        Route::post('/alerts/bulk-assign', [V1AlertController::class, 'bulkAssign']);
        Route::post('/alerts/bulk-resolve', [V1AlertController::class, 'bulkResolve']);
        Route::post('/alerts/auto-assign', [V1AlertController::class, 'autoAssign']);
        Route::get('/alerts/{id}', [V1AlertController::class, 'show']);

        // Cases API
        Route::get('/cases', [V1CaseController::class, 'index']);
        Route::post('/cases', [V1CaseController::class, 'store']);
        Route::get('/cases/{id}', [V1CaseController::class, 'show']);
        Route::patch('/cases/{id}', [V1CaseController::class, 'update']);
        Route::post('/cases/{id}/notes', [V1CaseController::class, 'addNote']);
        Route::post('/cases/{id}/close', [V1CaseController::class, 'close']);
        Route::post('/cases/{id}/escalate', [V1CaseController::class, 'escalate']);
        Route::get('/cases/{id}/timeline', [V1CaseController::class, 'timeline']);

        // EDD API
        Route::get('/edd', [V1EddController::class, 'index']);
        Route::get('/edd/templates', [V1EddController::class, 'templates']);
        Route::get('/edd/{id}', [V1EddController::class, 'show']);
        Route::post('/edd/{id}/questionnaire', [V1EddController::class, 'submitQuestionnaire']);
        Route::post('/edd/{id}/approve', [V1EddController::class, 'approve']);
        Route::post('/edd/{id}/reject', [V1EddController::class, 'reject']);
    });

    // Risk API
    Route::get('/risk/portfolio', [V1RiskController::class, 'portfolio']);
    Route::get('/risk/{customerId}', [V1RiskController::class, 'show']);
    Route::get('/risk/{customerId}/history', [V1RiskController::class, 'history']);
    Route::post('/risk/{customerId}/recalculate', [V1RiskController::class, 'recalculate']);
    Route::post('/risk/{customerId}/lock', [V1RiskController::class, 'lock']);
    Route::post('/risk/{customerId}/unlock', [V1RiskController::class, 'unlock']);

    // Dashboard API
    Route::prefix('compliance')->group(function () {
        Route::get('/dashboard', [V1DashboardController::class, 'kpis']);
        Route::get('/calendar', [V1DashboardController::class, 'calendar']);
        Route::get('/case-aging', [V1DashboardController::class, 'caseAging']);
        Route::get('/audit-trail', [V1DashboardController::class, 'auditTrail']);
        Route::get('/audit-trail/export', [V1DashboardController::class, 'auditTrailExport']);
        Route::get('/reports/auto', [V1DashboardController::class, 'autoReports']);
    });
});
