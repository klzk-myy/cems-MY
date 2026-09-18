<?php

namespace App\Http\Controllers\Api\V1\Compliance;

use App\Enums\EddStatus;
use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Compliance\EddIndexRequest;
use App\Http\Requests\Api\V1\Compliance\RejectEddRequest;
use App\Http\Requests\Api\V1\Compliance\SubmitQuestionnaireRequest;
use App\Http\Resources\Api\V1\EddRecordResource;
use App\Http\Resources\Api\V1\EddTemplateResource;
use App\Models\Compliance\EddQuestionnaireTemplate;
use App\Models\EnhancedDiligenceRecord;
use Illuminate\Http\JsonResponse;

class EddController extends Controller
{
    use ApiResponse;

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

        if (! $record->status->canSubmitQuestionnaire()) {
            return $this->errorResponse('Cannot submit questionnaire in current status.', [], 422);
        }

        $record->update([
            'questionnaire_responses' => $validated['responses'],
            'questionnaire_completed_at' => now(),
            'questionnaire_completed_by' => auth()->id(),
            'status' => EddStatus::QuestionnaireSubmitted,
        ]);

        return $this->successResponse(new EddRecordResource($record->fresh()), 'Questionnaire submitted successfully.');
    }

    /**
     * Statuses a record must be in before it can be finalised (approved or
     * rejected). The API flow submits the questionnaire (QuestionnaireSubmitted)
     * and may pass through Pending Review; incomplete or pre-questionnaire
     * records must not be finalised.
     */
    private function finalisableStatuses(): array
    {
        return [EddStatus::QuestionnaireSubmitted, EddStatus::PendingReview];
    }

    /**
     * Approve an EDD record.
     */
    public function approve(int $id): JsonResponse
    {
        $record = EnhancedDiligenceRecord::with('flaggedTransaction')->findOrFail($id);

        if (! in_array($record->status, $this->finalisableStatuses(), true)) {
            return $this->errorResponse(
                'Only records with a submitted questionnaire or in Pending Review can be approved (current: '.$record->status->value.').',
                [],
                422
            );
        }

        $record->update([
            'status' => EddStatus::Approved,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return $this->successResponse(new EddRecordResource($record), 'EDD record approved.');
    }

    /**
     * Reject an EDD record.
     */
    public function reject(RejectEddRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();

        $record = EnhancedDiligenceRecord::findOrFail($id);

        if (! in_array($record->status, $this->finalisableStatuses(), true)) {
            return $this->errorResponse(
                'Only records with a submitted questionnaire or in Pending Review can be rejected (current: '.$record->status->value.').',
                [],
                422
            );
        }

        $record->update([
            'status' => EddStatus::Rejected,
            'review_notes' => $validated['reason'],
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return $this->successResponse(new EddRecordResource($record), 'EDD record rejected.');
    }
}
