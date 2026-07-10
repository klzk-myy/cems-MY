<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Domain\MonthEndPreCheckFailedException;
use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MonthEndCloseRequest;
use App\Services\Accounting\MonthEndCloseService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MonthEndCloseController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected MonthEndCloseService $monthEndCloseService
    ) {}

    public function close(MonthEndCloseRequest $request): JsonResponse
    {
        $date = $request->input('date')
            ? Carbon::parse($request->input('date'))
            : now()->subMonth()->endOfMonth();

        $user = $request->user();

        try {
            $results = $this->monthEndCloseService->runMonthEndClosing($date, $user);

            return $this->successResponse($results);
        } catch (MonthEndPreCheckFailedException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Pre-flight checks failed',
                'failures' => $e->getFailures(),
            ], 422);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), [], 500);
        }
    }

    public function status(Request $request, string $date): JsonResponse
    {
        try {
            $carbonDate = Carbon::parse($date);
            $status = $this->monthEndCloseService->getMonthEndStatus($carbonDate);

            return $this->successResponse($status);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), [], 500);
        }
    }
}
