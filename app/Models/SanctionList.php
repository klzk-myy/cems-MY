<?php

namespace App\Models;

use App\Enums\SanctionListType;
use App\Enums\UpdateStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        'source_file',
        'uploaded_by',
        'auto_updated_by',
        'is_active',
        'uploaded_at',
        'last_updated_at',
        'last_attempted_at',
        'update_status',
        'last_error_message',
        'entry_count',
        'last_checksum',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'uploaded_at' => 'datetime',
        'last_updated_at' => 'datetime',
        'last_attempted_at' => 'datetime',
        'entry_count' => 'integer',
        'list_type' => SanctionListType::class,
        'update_status' => UpdateStatus::class,
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->uploaded_at = now();
        });
    }

    public function entries(): HasMany
    {
        return $this->hasMany(SanctionEntry::class, 'list_id');
    }

    public function importLogs(): HasMany
    {
        return $this->hasMany(SanctionImportLog::class, 'list_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

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
        return match ($this->update_status) {
            'success' => 'badge-success',
            'failed' => 'badge-error',
            'pending' => 'badge-warning',
            default => 'badge-neutral',
        };
    }
}
