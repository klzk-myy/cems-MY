<?php

namespace App\Http\Controllers\Api\V1\Compliance;

use App\Enums\EddStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Compliance\EddIndexRequest;
use App\Http\Requests\Api\V1\Compliance\RejectEddRequest;
use App\Http\Requests\Api\V1\Compliance\SubmitQuestionnaireRequest;
use App\Models\Compliance\EddQuestionnaireTemplate;
use App\Models\EnhancedDiligenceRecord;
use Illuminate\Http\JsonResponse;

class EddController extends Controller
{
    /**
     * List EDD records with filtering.
     */
    public function index(EddIndexRequest $request): JsonResponse
    {
        $query = EnhancedDiligenceRecord::with(['customer', 'flaggedTransaction']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('risk_level')) {
            $query->where('risk_level', $request->input('risk_level'));
        }

        $perPage = $request->get('per_page', 20);
        $records = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $records,
        ]);
    }

    /**
     * Get a specific EDD record.
     */
    public function show(int $id): JsonResponse
    {
        $record = EnhancedDiligenceRecord::with(['customer', 'flaggedTransaction'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $record,
        ]);
    }

    /**
     * List active questionnaire templates.
     */
    public function templates(): JsonResponse
    {
        $templates = EddQuestionnaireTemplate::getActiveTemplates()
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $templates,
        ]);
    }

    /**
     * Submit questionnaire for an EDD record.
     */
    public function submitQuestionnaire(SubmitQuestionnaireRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();

        $record = EnhancedDiligenceRecord::findOrFail($id);

        if (! $record->status->canSubmitQuestionnaire()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot submit questionnaire in current status.',
            ], 422);
        }

        $record->update([
            'questionnaire_responses' => $validated['responses'],
            'questionnaire_completed_at' => now(),
            'questionnaire_completed_by' => auth()->id(),
            'status' => EddStatus::QuestionnaireSubmitted,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Questionnaire submitted successfully.',
            'data' => $record->fresh(),
        ]);
    }

    /**
     * Approve an EDD record.
     */
    public function approve(int $id): JsonResponse
    {
        $record = EnhancedDiligenceRecord::with('flaggedTransaction')->findOrFail($id);

        $record->update([
            'status' => EddStatus::Approved,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'EDD record approved.',
            'data' => $record,
        ]);
    }

    /**
     * Reject an EDD record.
     */
    public function reject(RejectEddRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();

        $record = EnhancedDiligenceRecord::findOrFail($id);

        $record->update([
            'status' => EddStatus::Rejected,
            'review_notes' => $validated['reason'],
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'EDD record rejected.',
            'data' => $record,
        ]);
    }
}
