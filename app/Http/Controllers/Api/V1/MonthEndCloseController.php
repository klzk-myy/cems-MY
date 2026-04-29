<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Domain\MonthEndPreCheckFailedException;
use App\Http\Controllers\Controller;
use App\Services\MonthEndCloseService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MonthEndCloseController extends Controller
{
    public function __construct(
        protected MonthEndCloseService $monthEndCloseService
    ) {}

    public function close(Request $request): JsonResponse
    {
        $date = $request->input('date')
            ? Carbon::parse($request->input('date'))
            : now()->subMonth()->endOfMonth();

        $user = $request->user();

        try {
            $results = $this->monthEndCloseService->runMonthEndClosing($date, $user);

            return response()->json([
                'success' => true,
                'data' => $results,
            ]);
        } catch (MonthEndPreCheckFailedException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Pre-flight checks failed',
                'failures' => $e->getFailures(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function status(Request $request, string $date): JsonResponse
    {
        try {
            $carbonDate = Carbon::parse($date);
            $status = $this->monthEndCloseService->getMonthEndStatus($carbonDate);

            return response()->json([
                'success' => true,
                'data' => $status,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
