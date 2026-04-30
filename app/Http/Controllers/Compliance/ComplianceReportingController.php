<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateReportScheduleRequest;
use App\Http\Requests\RunReportRequest;
use App\Http\Requests\UpdateReportScheduleRequest;
use App\Models\ReportRun;
use App\Models\ReportSchedule;
use App\Services\DocumentStorageService;
use App\Services\ReportSchedulingService;
use Illuminate\Http\Request;

class ComplianceReportingController extends Controller
{
    public function __construct(
        protected ReportSchedulingService $reportingService,
        protected DocumentStorageService $documentStorageService
    ) {}

    public function index()
    {
        $summary = $this->reportingService->getDashboardSummary();
        $kpis = $this->reportingService->getKpiMetrics();
        $deadlines = $this->reportingService->getDeadlineCalendar();

        return view('compliance.reporting.index', compact('summary', 'kpis', 'deadlines'));
    }

    public function generate(Request $request)
    {
        $reportTypes = ReportSchedule::getReportTypes();

        return view('compliance.reporting.generate', compact('reportTypes'));
    }

    public function run(RunReportRequest $request)
    {

        $params = [];
        if ($request->has('date')) {
            $params['date'] = $request->date;
        } elseif ($request->has('month')) {
            $params['month'] = $request->month;
        } elseif ($request->has('quarter')) {
            $params['quarter'] = $request->quarter;
        }

        $reportRun = $this->reportingService->generateReport(
            $request->report_type,
            $params,
            auth()->id()
        );

        return redirect()->route('compliance.reporting.history')
            ->with('success', 'Report generated successfully');
    }

    public function history(Request $request)
    {
        $filters = $request->only(['report_type', 'status', 'from_date', 'to_date']);

        $reports = $this->reportingService->getReportHistory($filters)->paginate(25);

        return view('compliance.reporting.history', compact('reports', 'filters'));
    }

    public function download(ReportRun $report)
    {
        if (! $report->file_path || ! $this->documentStorageService->exists($report->file_path)) {
            abort(404, 'Report file not found');
        }

        $report->increment('downloaded_count');

        return $this->documentStorageService->download($report->file_path);
    }

    public function schedule()
    {
        $schedules = ReportSchedule::with('createdBy')
            ->orderByDesc('is_active')
            ->orderBy('next_run_at')
            ->paginate(25);

        $reportTypes = ReportSchedule::getReportTypes();

        return view('compliance.reporting.schedule', compact('schedules', 'reportTypes'));
    }

    public function createSchedule(CreateReportScheduleRequest $request)
    {

        $schedule = $this->reportingService->createSchedule([
            'report_type' => $request->report_type,
            'cron_expression' => $request->cron_expression,
            'parameters' => $request->parameters ?? [],
            'notification_recipients' => $request->notification_recipients ?? [],
            'created_by' => auth()->id(),
        ]);

        return redirect()->back()->with('success', 'Schedule created successfully');
    }

    public function updateSchedule(UpdateReportScheduleRequest $request, ReportSchedule $schedule)
    {

        $schedule = $this->reportingService->updateSchedule($schedule, $request->all());

        return redirect()->back()->with('success', 'Schedule updated successfully');
    }

    public function deleteSchedule(ReportSchedule $schedule)
    {
        $schedule->delete();

        return redirect()->route('compliance.reporting.schedule')
            ->with('success', 'Schedule deleted successfully');
    }

    public function deadlines()
    {
        $deadlines = $this->reportingService->getDeadlineCalendar();

        return view('compliance.reporting.deadlines', compact('deadlines'));
    }
}
