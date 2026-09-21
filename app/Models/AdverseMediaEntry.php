<?php

namespace App\Models;

use App\Models\Compliance\ScreeningResult;
use Database\Factories\AdverseMediaEntryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Adverse media screening entry (news article alleging financial crime,
 * corruption, etc.) used as a parallel candidate pool to SanctionEntry.
 *
 * @property int $id
 * @property string $name
 * @property string|null $normalized_name
 * @property string|null $alias
 * @property string $article_title
 * @property string $source
 * @property string|null $url
 * @property string|null $snippet
 * @property Carbon|null $published_at
 * @property string $severity 'low', 'medium', 'high'
 * @property bool $is_active
 * @property string $record_hash sha256(name|source|url) for idempotent upsert
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AdverseMediaEntry extends BaseModel
{
    /** @use HasFactory<AdverseMediaEntryFactory> */
    use HasFactory;

    final public const SEVERITY_LOW = 'low';

    final public const SEVERITY_MEDIUM = 'medium';

    final public const SEVERITY_HIGH = 'high';

    public const SEVERITIES = [
        self::SEVERITY_LOW,
        self::SEVERITY_MEDIUM,
        self::SEVERITY_HIGH,
    ];

    protected $fillable = [
        'name',
        'normalized_name',
        'alias',
        'article_title',
        'source',
        'url',
        'snippet',
        'published_at',
        'severity',
        'is_active',
        'record_hash',
    ];

    protected $casts = [
        'published_at' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Stable upsert key across imports: same name+source+url updates the
     * existing row instead of duplicating it.
     */
    public static function buildRecordHash(string $name, string $source, ?string $url): string
    {
        return hash('sha256', mb_strtolower(trim($name)).'|'.mb_strtolower(trim($source)).'|'.mb_strtolower(trim((string) $url)));
    }

    /**
     * @return HasMany<ScreeningResult, $this>
     */
    public function screeningResults(): HasMany
    {
        return $this->hasMany(ScreeningResult::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOfSeverity(Builder $query, string $severity): Builder
    {
        return $query->where('severity', $severity);
    }
}
