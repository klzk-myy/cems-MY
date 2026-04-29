<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchClosureWorkflow extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'initiated_by',
        'status',
        'checklist',
        'settlement_at',
        'finalized_at',
    ];

    protected $casts = [
        'checklist' => 'array',
        'settlement_at' => 'datetime',
        'finalized_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function isInitiated(): bool
    {
        return $this->status === 'initiated';
    }

    public function isSettled(): bool
    {
        return $this->status === 'settled';
    }

    public function isFinalized(): bool
    {
        return $this->status === 'finalized';
    }

    public function markSettled(): void
    {
        $this->update([
            'status' => 'settled',
            'settlement_at' => now(),
        ]);
    }

    public function markFinalized(): void
    {
        $this->update([
            'status' => 'finalized',
            'finalized_at' => now(),
        ]);
    }
}
