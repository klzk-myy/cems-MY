<?php

namespace App\Services;

use App\Enums\EddRiskLevel;
use App\Enums\EddStatus;
use App\Enums\EddTemplateType;
use App\Models\Customer;
use App\Models\EddTemplate;
use App\Models\EnhancedDiligenceRecord;
use Illuminate\Support\Collection;

class EddTemplateService
{
    /**
     * Create an EDD record from a template.
     */
    public function createFromTemplate(
        int $templateId,
        int $customerId,
        array $context = []
    ): EnhancedDiligenceRecord {
        $template = EddTemplate::findOrFail($templateId);

        $record = EnhancedDiligenceRecord::create([
            'customer_id' => $customerId,
            'template_id' => $template->id,
            'status' => EddStatus::Incomplete,
            'risk_level' => $this->determineInitialRiskLevel($context),
            'questions' => $this->buildConditionalQuestions($template, $context),
            'responses' => [],
            'submitted_at' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'notes' => $context['notes'] ?? null,
        ]);

        return $record;
    }

    /**
     * Get active templates by type.
     */
    public function getActiveTemplatesByType(EddTemplateType $type): Collection
    {
        return EddTemplate::active()->byType($type)->get();
    }

    /**
     * Get all active templates.
     */
    public function getAllActiveTemplates(): Collection
    {
        return EddTemplate::active()->withCount('enhancedDiligenceRecords')->get();
    }

    /**
     * Build conditional question tree based on context.
     */
    protected function buildConditionalQuestions(EddTemplate $template, array $context): array
    {
        $questions = $template->questions;
        $filtered = ['sections' => []];

        foreach ($questions['sections'] ?? [] as $section) {
            $filteredQuestions = [];

            foreach ($section['questions'] ?? [] as $question) {
                if ($this->shouldIncludeQuestion($question, $context)) {
                    $filteredQuestions[] = $question;
                }
            }

            if (! empty($filteredQuestions)) {
                $filtered['sections'][] = [
                    'title' => $section['title'],
                    'questions' => $filteredQuestions,
                ];
            }
        }

        return $filtered;
    }

    /**
     * Determine if a question should be included based on conditions.
     */
    protected function shouldIncludeQuestion(array $question, array $context): bool
    {
        $condition = $question['condition'] ?? null;

        if (empty($condition)) {
            return true;
        }

        $questionId = $condition['question'] ?? null;
        $expectedValue = $condition['value'] ?? null;

        if (empty($questionId) || $expectedValue === null) {
            return true;
        }

        $actualValue = $context[$questionId] ?? null;

        return $actualValue === $expectedValue;
    }

    /**
     * Determine initial risk level based on context.
     */
    protected function determineInitialRiskLevel(array $context): EddRiskLevel
    {
        if ($context['is_pep'] ?? false) {
            return EddRiskLevel::High;
        }

        if ($context['is_sanctioned'] ?? false) {
            return EddRiskLevel::Critical;
        }

        if ($context['transaction_amount'] ?? 0 >= 50000) {
            return EddRiskLevel::High;
        }

        if ($context['high_risk_country'] ?? false) {
            return EddRiskLevel::High;
        }

        if ($context['unusual_pattern'] ?? false) {
            return EddRiskLevel::Medium;
        }

        return EddRiskLevel::Medium;
    }

    /**
     * Validate EDD responses.
     */
    public function validateResponses(EnhancedDiligenceRecord $record, array $responses): array
    {
        $errors = [];
        $questions = $record->questions;

        foreach ($questions['sections'] ?? [] as $section) {
            foreach ($section['questions'] ?? [] as $question) {
                $questionId = $question['id'] ?? null;
                $isRequired = $question['required'] ?? false;

                if ($isRequired && empty($responses[$questionId])) {
                    $errors[$questionId] = 'This field is required';
                }
            }
        }

        return $errors;
    }

    /**
     * Submit EDD record for review.
     */
    public function submitForReview(EnhancedDiligenceRecord $record, array $responses): EnhancedDiligenceRecord
    {
        $errors = $this->validateResponses($record, $responses);

        if (! empty($errors)) {
            throw new \InvalidArgumentException('Response validation failed: '.implode(', ', $errors));
        }

        $record->update([
            'responses' => $responses,
            'status' => EddStatus::PendingReview,
            'submitted_at' => now(),
        ]);

        return $record->fresh();
    }

    /**
     * Approve EDD record.
     */
    public function approve(EnhancedDiligenceRecord $record, int $reviewedBy, ?string $notes = null): EnhancedDiligenceRecord
    {
        $record->update([
            'status' => EddStatus::Approved,
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now(),
            'notes' => $notes ?? $record->notes,
        ]);

        return $record->fresh();
    }

    /**
     * Reject EDD record.
     */
    public function reject(EnhancedDiligenceRecord $record, int $reviewedBy, string $reason): EnhancedDiligenceRecord
    {
        $record->update([
            'status' => EddStatus::Rejected,
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now(),
            'notes' => $reason,
        ]);

        return $record->fresh();
    }

    /**
     * Get template usage statistics.
     */
    public function getTemplateStatistics(): array
    {
        $templates = $this->getAllActiveTemplates();

        return $templates->map(function ($template) {
            return [
                'id' => $template->id,
                'name' => $template->name,
                'type' => $template->type->label(),
                'is_active' => $template->is_active,
                'usage_count' => $template->enhanced_diligence_records_count,
                'pending_count' => $template->enhancedDiligenceRecords()
                    ->where('status', EddStatus::PendingReview)->count(),
            ];
        })->toArray();
    }

    /**
     * Create a new template.
     */
    public function createTemplate(array $data): EddTemplate
    {
        return EddTemplate::create([
            'name' => $data['name'],
            'type' => $data['type'],
            'description' => $data['description'] ?? null,
            'questions' => $data['questions'] ?? ['sections' => []],
            'version' => 1,
            'is_active' => $data['is_active'] ?? true,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Update an existing template.
     */
    public function updateTemplate(EddTemplate $template, array $data): EddTemplate
    {
        $template->update([
            'name' => $data['name'] ?? $template->name,
            'description' => $data['description'] ?? $template->description,
            'questions' => $data['questions'] ?? $template->questions,
        ]);

        if (isset($data['is_active'])) {
            $template->update(['is_active' => $data['is_active']]);
        }

        return $template->fresh();
    }

    /**
     * Get recommended template for a customer/context.
     */
    public function getRecommendedTemplate(array $context): ?EddTemplate
    {
        if ($context['is_sanctioned'] ?? false) {
            return EddTemplate::active()->byType(EddTemplateType::SanctionMatch)->first();
        }

        if ($context['is_pep'] ?? false) {
            return EddTemplate::active()->byType(EddTemplateType::Pep)->first();
        }

        if ($context['high_risk_country'] ?? false) {
            return EddTemplate::active()->byType(EddTemplateType::HighRiskCountry)->first();
        }

        if ($context['transaction_amount'] ?? 0 >= 50000) {
            return EddTemplate::active()->byType(EddTemplateType::LargeTransaction)->first();
        }

        if ($context['unusual_pattern'] ?? false) {
            return EddTemplate::active()->byType(EddTemplateType::UnusualPattern)->first();
        }

        return EddTemplate::active()->first();
    }
}
