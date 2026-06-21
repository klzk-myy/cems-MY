<?php

namespace App\Models;

use App\Enums\TransactionImportStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TransactionImport extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'filename',
        'original_filename',
        'file_hash',
        'file_size',
        'status',
        'total_rows',
        'processed_rows',
        'success_count',
        'error_count',
        'error_details',
        'imported_by',
        'imported_at',
        'completed_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'total_rows' => 'integer',
        'processed_rows' => 'integer',
        'success_count' => 'integer',
        'error_count' => 'integer',
        'error_details' => 'array',
        'imported_at' => 'datetime',
        'completed_at' => 'datetime',
        'status' => TransactionImportStatus::class,
    ];

    /**
     * Relationship: The user who imported the file
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    /**
     * Scope: Get completed imports
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', TransactionImportStatus::Completed->value);
    }

    /**
     * Scope: Get pending imports
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', TransactionImportStatus::Pending->value);
    }

    /**
     * Check if import has errors
     */
    public function hasErrors(): bool
    {
        return $this->error_count > 0;
    }

    /**
     * Get errors array
     */
    public function getErrors(): array
    {
        return $this->error_details ?? [];
    }

    /**
     * Get formatted status badge color
     */
    public function getStatusColor(): string
    {
        return match ($this->status) {
            'completed' => 'success',
            'processing' => 'warning',
            'failed' => 'danger',
            default => 'secondary',
        };
    }
}
