<?php

namespace App\Http\Controllers;

use App\Enums\ReportType;
use App\Http\Requests\CreateReportScheduleRequest;
use App\Http\Requests\UpdateReportScheduleRequest;
use App\Models\ReportSchedule;
use App\Services\Reporting\ReportSchedulingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportScheduleController extends Controller
{
    public function __construct(
        protected ReportSchedulingService $reportSchedulingService,
    ) {}

    /**
     * List all report schedules.
     */
    public function index(Request $request): View
    {
        $query = ReportSchedule::query()->orderByDesc('created_at');

        $type = $request->string('type')->toString();
        if ($type !== '' && ReportType::tryFrom($type)) {
            $query->where('report_type', $type);
        }

        $status = $request->string('status')->toString();
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $schedules = $query->paginate(15)->withQueryString();

        return view('reports.schedules.index', [
            'schedules' => $schedules,
            'reportTypes' => collect(ReportType::cases())->map(fn (ReportType $t) => [
                'value' => $t->value,
                'label' => $t->value,
            ]),
        ]);
    }

    /**
     * Show the form for creating a new report schedule.
     */
    public function create(): View
    {
        $reportTypes = ReportType::cases();

        return view('reports.schedules.create', compact('reportTypes'));
    }

    /**
     * Store a newly created report schedule.
     */
    public function store(CreateReportScheduleRequest $request): RedirectResponse
    {
        $this->reportSchedulingService->createSchedule($request->validated());

        return redirect()->route('reports.schedules.index')->with('success', 'Report schedule created successfully.');
    }

    /**
     * Show the specified report schedule.
     */
    public function show(ReportSchedule $schedule): View
    {
        $recentRuns = $schedule->reportRuns()
            ->latest()
            ->limit(10)
            ->get();

        return view('reports.schedules.show', compact('schedule', 'recentRuns'));
    }

    /**
     * Pause the specified report schedule.
     */
    public function pause(ReportSchedule $schedule): RedirectResponse
    {
        $schedule->update(['is_active' => false]);

        return redirect()->route('reports.schedules.show', $schedule)->with('success', 'Report schedule paused.');
    }

    /**
     * Resume the specified report schedule.
     */
    public function resume(ReportSchedule $schedule): RedirectResponse
    {
        $schedule->update(['is_active' => true]);
        $schedule->updateNextRun();

        return redirect()->route('reports.schedules.show', $schedule)->with('success', 'Report schedule resumed.');
    }

    /**
     * Show the form for editing the specified report schedule.
     */
    public function edit(ReportSchedule $schedule): View
    {
        $reportTypes = ReportType::cases();

        return view('reports.schedules.edit', compact('schedule', 'reportTypes'));
    }

    /**
     * Update the specified report schedule.
     */
    public function update(UpdateReportScheduleRequest $request, ReportSchedule $schedule): RedirectResponse
    {
        $this->reportSchedulingService->updateSchedule($schedule, $request->validated());

        return redirect()->route('reports.schedules.show', $schedule)->with('success', 'Report schedule updated successfully.');
    }

    /**
     * Remove the specified report schedule.
     */
    public function destroy(ReportSchedule $schedule): RedirectResponse
    {
        $schedule->delete();

        return redirect()->route('reports.schedules.index')->with('success', 'Report schedule deleted successfully.');
    }
}
