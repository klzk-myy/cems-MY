<?php

namespace App\Http\Controllers\Api\V1\Compliance;

use App\Exceptions\Domain\EddValidationException;
use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Compliance\EddIndexRequest;
use App\Http\Requests\Api\V1\Compliance\RejectEddRequest;
use App\Http\Requests\Api\V1\Compliance\SubmitQuestionnaireRequest;
use App\Http\Resources\Api\V1\EddRecordResource;
use App\Http\Resources\Api\V1\EddTemplateResource;
use App\Models\Compliance\EddQuestionnaireTemplate;
use App\Models\Compliance\EnhancedDiligenceRecord;
use App\Services\Compliance\EddService;
use Illuminate\Http\JsonResponse;

class EddController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected EddService $eddService
    ) {}

    /**
     * List EDD records with filtering.
     */
    public function index(EddIndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', EnhancedDiligenceRecord::class);

        $query = EnhancedDiligenceRecord::with(['customer', 'flaggedTransaction']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('risk_level')) {
            $query->where('risk_level', $request->input('risk_level'));
        }

        $perPage = min(100, max(1, (int) $request->get('per_page', 20)));
        $records = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->resourceResponse(EddRecordResource::collection($records), 'EDD records retrieved successfully.');
    }

    /**
     * Get a specific EDD record.
     */
    public function show(int $id): JsonResponse
    {
        $record = EnhancedDiligenceRecord::with(['customer', 'flaggedTransaction'])
            ->findOrFail($id);

        $this->authorize('view', $record);

        return $this->successResponse(new EddRecordResource($record), 'EDD record retrieved successfully.');
    }

    /**
     * List active questionnaire templates.
     */
    public function templates(): JsonResponse
    {
        $templates = EddQuestionnaireTemplate::getActiveTemplates()
            ->orderBy('name')
            ->get();

        return $this->successResponse(EddTemplateResource::collection($templates), 'EDD templates retrieved successfully.');
    }

    /**
     * Submit questionnaire for an EDD record.
     */
    public function submitQuestionnaire(SubmitQuestionnaireRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();

        $record = EnhancedDiligenceRecord::findOrFail($id);

        try {
            $record = $this->eddService->submitQuestionnaire($record, $validated['responses'], (int) auth()->id());
        } catch (EddValidationException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        }

        return $this->successResponse(new EddRecordResource($record), 'Questionnaire submitted successfully.');
    }

    /**
     * Approve an EDD record.
     */
    public function approve(int $id): JsonResponse
    {
        $record = EnhancedDiligenceRecord::with('flaggedTransaction')->findOrFail($id);

        try {
            $record = $this->eddService->approve($record, auth()->user());
        } catch (EddValidationException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        }

        return $this->successResponse(new EddRecordResource($record), 'EDD record approved.');
    }

    /**
     * Reject an EDD record.
     */
    public function reject(RejectEddRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();

        $record = EnhancedDiligenceRecord::findOrFail($id);

        try {
            $record = $this->eddService->reject($record, auth()->user(), $validated['reason']);
        } catch (EddValidationException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        }

        return $this->successResponse(new EddRecordResource($record), 'EDD record rejected.');
    }
}
