<?php

namespace App\Models\Compliance;

use App\Enums\SanctionListType;
use App\Enums\SanctionSourceFormat;
use App\Enums\UpdateStatus;
use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property SanctionListType $list_type 'UNSCR', 'MOHA', 'Domestic', 'Internal'
 * @property string|null $source_url
 * @property SanctionSourceFormat|null $source_format
 * @property string|null $source_file
 * @property int $uploaded_by
 * @property bool $is_active
 * @property Carbon $uploaded_at
 * @property Carbon|null $last_updated_at
 * @property Carbon|null $last_attempted_at
 * @property UpdateStatus $update_status 'success', 'failed', 'pending', 'never_run'
 * @property string|null $last_error_message
 * @property int $entry_count
 * @property string|null $last_checksum
 * @property string|null $last_dataset_version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $auto_updated_by
 * @property Carbon|null $deleted_at
 * @property-read string $update_status_badge
 */
class SanctionList extends BaseModel
{
    use HasFactory, SoftDeletes;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'slug',
        'list_type',
        'source_url',
        'source_format',
        'uploaded_by',
        'is_active',
        'uploaded_at',
        'last_updated_at',
        'last_attempted_at',
        'update_status',
        'last_error_message',
        'entry_count',
        'last_checksum',
        'last_dataset_version',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'uploaded_at' => 'datetime',
        'last_updated_at' => 'datetime',
        'last_attempted_at' => 'datetime',
        'entry_count' => 'integer',
        'list_type' => SanctionListType::class,
        'source_format' => SanctionSourceFormat::class,
        'update_status' => UpdateStatus::class,
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->uploaded_at = now();
        });
    }

    /**
     * @return HasMany<SanctionEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(SanctionEntry::class, 'list_id');
    }

    /**
     * @return HasMany<SanctionImportLog, $this>
     */
    public function importLogs(): HasMany
    {
        return $this->hasMany(SanctionImportLog::class, 'list_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function autoUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auto_updated_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeAutoUpdatable($query)
    {
        return $query->whereNotNull('source_url')->where('is_active', true);
    }

    public function isAutoUpdated(): bool
    {
        return $this->auto_updated_by !== null;
    }

    public function getUpdateStatusBadgeAttribute(): string
    {
        return match ($this->update_status->value) {
            'success' => 'badge-success',
            'failed' => 'badge-error',
            'pending' => 'badge-warning',
            default => 'badge-neutral',
        };
    }
}
