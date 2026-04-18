<?php

namespace App\Models;

use App\Enums\AlertPriority;
use App\Enums\ComplianceFlagType;
use App\Enums\FlagStatus;
use App\Models\Compliance\ComplianceCase;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    use HasFactory;

    protected $fillable = [
        'flagged_transaction_id',
        'customer_id',
        'type',
        'priority',
        'risk_score',
        'reason',
        'source',
        'assigned_to',
        'case_id',
        'status',
    ];

    protected $casts = [
        'type' => ComplianceFlagType::class,
        'priority' => AlertPriority::class,
        'status' => FlagStatus::class,
        'risk_score' => 'integer',
    ];

    public function flaggedTransaction(): BelongsTo
    {
        return $this->belongsTo(FlaggedTransaction::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(ComplianceCase::class, 'case_id');
    }

    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_to');
    }

    public function scopeByPriority($query, AlertPriority $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeOpen($query)
    {
        return $query->whereNull('case_id');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', FlagStatus::Resolved);
    }

    public function calculateSlaDeadline(): \DateTime
    {
        return now()->addHours($this->priority->slaHours());
    }

    public function isOverdue(): bool
    {
        if ($this->case_id) {
            return false;
        }

        return now()->isAfter($this->calculateSlaDeadline());
    }

    public function isResolved(): bool
    {
        return $this->status === FlagStatus::Resolved;
    }
}
