<?php

namespace App\Models;

use App\Enums\ComplianceFlagType;
use App\Enums\FlagStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlaggedTransaction extends BaseModel
{
    use HasFactory;

    protected $with = ['transaction', 'customer', 'assignedTo', 'reviewer'];

    protected $fillable = [
        'transaction_id',
        'flag_type',
        'flag_reason',
        'status',
        'assigned_to',
        'reviewed_by',
        'notes',
        'resolved_at',
        'customer_id',
    ];

    protected $casts = [
        'flag_type' => ComplianceFlagType::class,
        'status' => FlagStatus::class,
        'resolved_at' => 'datetime',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
