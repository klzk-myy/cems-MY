<?php

namespace App\Http\Concerns;

use App\Enums\FindingStatus;
use App\Models\Compliance\ComplianceFinding;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;

trait FiltersComplianceFindings
{
    protected function applyFindingFilters(Builder $query, FormRequest $request, string $dateFromKey = 'date_from', string $dateToKey = 'date_to'): void
    {
        $validated = $request->validated();

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['severity'])) {
            $query->where('severity', $validated['severity']);
        }
        if (! empty($validated['type'])) {
            $query->where('finding_type', $validated['type']);
        }
        if (! empty($validated[$dateFromKey])) {
            $query->where('generated_at', '>=', Carbon::parse($validated[$dateFromKey])->startOfDay());
        }
        if (! empty($validated[$dateToKey])) {
            $query->where('generated_at', '<=', Carbon::parse($validated[$dateToKey])->endOfDay());
        }
    }

    protected function getFindingStats(): array
    {
        $total = ComplianceFinding::count();
        $newCount = ComplianceFinding::where('status', FindingStatus::New->value)->count();

        $bySeverity = ComplianceFinding::query()
            ->selectRaw('severity, count(*) as count')
            ->groupBy('severity')
            ->pluck('count', 'severity');

        $byStatus = ComplianceFinding::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $byType = ComplianceFinding::query()
            ->selectRaw('finding_type, count(*) as count')
            ->groupBy('finding_type')
            ->pluck('count', 'finding_type');

        return [
            'total' => $total,
            'new' => $newCount,
            'by_severity' => $bySeverity,
            'by_status' => $byStatus,
            'by_type' => $byType,
        ];
    }
}
