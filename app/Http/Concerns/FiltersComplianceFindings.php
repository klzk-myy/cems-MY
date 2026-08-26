<?php

namespace App\Http\Concerns;

use App\Enums\FindingStatus;
use App\Models\Compliance\ComplianceFinding;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait FiltersComplianceFindings
{
    protected function applyFindingFilters(Builder $query, Request $request, string $dateFromKey = 'date_from', string $dateToKey = 'date_to'): void
    {
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('severity')) {
            $query->where('severity', $request->input('severity'));
        }
        if ($request->filled('type')) {
            $query->where('finding_type', $request->input('type'));
        }
        if ($request->has($dateFromKey)) {
            $query->whereDate('generated_at', '>=', $request->input($dateFromKey));
        }
        if ($request->has($dateToKey)) {
            $query->whereDate('generated_at', '<=', $request->input($dateToKey));
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
