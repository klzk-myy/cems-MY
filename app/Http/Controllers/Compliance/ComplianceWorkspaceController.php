<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use App\Services\AlertTriageService;
use App\Services\Compliance\CaseManagementService;
use App\Services\CustomerRiskScoringService;
use App\Services\EddTemplateService;
use App\Services\ReportSchedulingService;
use App\Services\StrAutomationService;

class ComplianceWorkspaceController extends Controller
{
    public function __construct(
        protected AlertTriageService $alertTriageService,
        protected CaseManagementService $caseManagementService,
        protected CustomerRiskScoringService $riskScoringService,
        protected ReportSchedulingService $reportingService,
        protected StrAutomationService $strAutomationService,
        protected EddTemplateService $eddTemplateService,
    ) {}

    public function index()
    {
        $alertSummary = $this->alertTriageService->getQueueSummary();
        $caseSummary = $this->caseManagementService->getCaseSummary();
        $riskSummary = $this->riskScoringService->getDashboardSummary();
        $reportSummary = $this->reportingService->getDashboardSummary();
        $strDeadlineSummary = $this->strAutomationService->getFilingDeadlineSummary();
        $kpis = $this->reportingService->getKpiMetrics();
        $deadlines = $this->reportingService->getDeadlineCalendar();

        return view('compliance.workspace.index', compact(
            'alertSummary',
            'caseSummary',
            'riskSummary',
            'reportSummary',
            'strDeadlineSummary',
            'kpis',
            'deadlines'
        ));
    }
}
