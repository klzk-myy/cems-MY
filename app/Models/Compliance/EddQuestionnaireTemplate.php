<?php

namespace App\Models\Compliance;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EddQuestionnaireTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'version',
        'is_active',
        'questions',
    ];

    protected $casts = [
        'questions' => 'array',
        'is_active' => 'boolean',
    ];

    public function scopeGetActiveTemplates(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getQuestionsBySection(): array
    {
        $questions = $this->questions ?? [];
        $grouped = [];

        foreach ($questions as $question) {
            $section = $question['section'] ?? 'general';
            if (! isset($grouped[$section])) {
                $grouped[$section] = [];
            }
            $grouped[$section][] = $question;
        }

        return $grouped;
    }

    public function isComplete(array $responses): bool
    {
        $questions = $this->questions ?? [];

        foreach ($questions as $question) {
            if (($question['required'] ?? false) === true) {
                $questionId = $question['id'] ?? null;
                if ($questionId === null || ! isset($responses[$questionId]) || empty($responses[$questionId])) {
                    return false;
                }
            }
        }

        return true;
    }
}
