<?php

namespace App\Livewire\Compliance\Ctos;

use App\Enums\CtosStatus;
use App\Livewire\BaseComponent;
use App\Models\CtosReport;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\WithPagination;

class Index extends BaseComponent
{
    use WithPagination;

    protected $queryString = [
        'status' => ['except' => ''],
        'from_date' => ['except' => ''],
        'to_date' => ['except' => ''],
    ];

    public string $status = '';

    public string $from_date = '';

    public string $to_date = '';

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->status = '';
        $this->from_date = '';
        $this->to_date = '';
        $this->resetPage();
    }

    public function submitToBnm(int $reportId): void
    {
        $report = CtosReport::findOrFail($reportId);
        if ($report->isDraft()) {
            $report->markAsSubmitted(auth()->id());
            $this->success('CTOS report submitted to BNM successfully.');
        }
    }

    protected function getReports(): LengthAwarePaginator
    {
        $query = CtosReport::with(['customer', 'transaction']);

        if (! empty($this->status)) {
            $query->where('status', $this->status);
        }

        if (! empty($this->from_date)) {
            $query->whereDate('report_date', '>=', $this->from_date);
        }

        if (! empty($this->to_date)) {
            $query->whereDate('report_date', '<=', $this->to_date);
        }

        return $query->orderByDesc('report_date')->paginate(20);
    }

    protected function getSummary(): array
    {
        return [
            'total' => CtosReport::count(),
            'draft' => CtosReport::where('status', CtosStatus::Draft->value)->count(),
            'submitted' => CtosReport::where('status', CtosStatus::Submitted->value)->count(),
            'acknowledged' => CtosReport::where('status', CtosStatus::Acknowledged->value)->count(),
            'rejected' => CtosReport::where('status', CtosStatus::Rejected->value)->count(),
        ];
    }

    public function render(): View
    {
        return view('livewire.compliance.ctos.index', [
            'reports' => $this->getReports(),
            'summary' => $this->getSummary(),
            'ctosStatuses' => CtosStatus::cases(),
        ]);
    }
}
