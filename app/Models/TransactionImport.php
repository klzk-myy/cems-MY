<?php

namespace App\Models;

use App\Enums\TransactionImportStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TransactionImport extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'filename',
        'original_filename',
        'total_rows',
        'success_count',
        'error_count',
        'errors',
        'status',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'errors' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'status' => TransactionImportStatus::class,
    ];

    /**
     * Relationship: The user who imported the file
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope: Get completed imports
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', TransactionImportStatus::Completed->value);
    }

    /**
     * Scope: Get pending imports
     */
    public function scopePending($query)
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
        return $this->errors ?? [];
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
