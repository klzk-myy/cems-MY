<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\FiscalYear;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Fiscal Year API Controller
 *
 * Provides API endpoints for fiscal year and accounting period data.
 */
class FiscalYearController extends Controller
{
    /**
     * Get accounting periods for a fiscal year.
     */
    public function periods(Request $request, int $fiscalYearId): JsonResponse
    {
        $fiscalYear = FiscalYear::find($fiscalYearId);

        if (! $fiscalYear) {
            return response()->json([
                'error' => 'Fiscal year not found',
            ], 404);
        }

        $periods = $fiscalYear->periods()
            ->orderBy('start_date')
            ->get(['id', 'period_code', 'period_type', 'start_date', 'end_date', 'status']);

        return response()->json($periods);
    }
}
