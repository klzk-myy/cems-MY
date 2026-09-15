<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Dual-purpose table: the latest row per (category, key) IS the live override
 * read by ThresholdService::get(), and the full history is the audit log.
 * Resets are appended as reverting rows, never deleted.
 *
 * @property int $id
 * @property string $category
 * @property string $key
 * @property string $old_value
 * @property string $new_value
 * @property int|null $changed_by
 * @property string|null $change_reason
 * @property Carbon $changed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
class ThresholdAudit extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'category',
        'key',
        'old_value',
        'new_value',
        'changed_by',
        'change_reason',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeKey(Builder $query, string $key): Builder
    {
        return $query->where('key', $key);
    }
}
