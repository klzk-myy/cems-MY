<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SanctionList extends Model
{
    use HasFactory;

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

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function autoUpdatedBy()
    {
        return $this->belongsTo(User::class, 'auto_updated_by');
    }

    public function scopeActive($query)
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
