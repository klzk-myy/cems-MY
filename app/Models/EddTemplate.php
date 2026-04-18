<?php

namespace App\Models;

use App\Enums\EddTemplateType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EddTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'description',
        'questions',
        'version',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'type' => EddTemplateType::class,
        'questions' => 'array',
        'is_active' => 'boolean',
        'version' => 'integer',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function enhancedDiligenceRecords(): HasMany
    {
        return $this->hasMany(EnhancedDiligenceRecord::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, EddTemplateType $type)
    {
        return $query->where('type', $type);
    }

    public function getSections(): array
    {
        return $this->questions['sections'] ?? [];
    }

    public function getTotalQuestions(): int
    {
        $sections = $this->getSections();
        $count = 0;
        foreach ($sections as $section) {
            $count += count($section['questions'] ?? []);
        }

        return $count;
    }

    public function duplicate(): static
    {
        $clone = $this->replicate();
        $clone->name = $this->name.' (Copy)';
        $clone->version = 1;
        $clone->is_active = false;
        $clone->save();

        return $clone;
    }
}
