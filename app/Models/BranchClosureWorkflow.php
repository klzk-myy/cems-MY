<?php

namespace App\Models;

use App\Enums\BranchClosureStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $branch_id
 * @property int $initiated_by
 * @property BranchClosureStatus $status
 * @property array|null $checklist
 * @property Carbon|null $settlement_at
 * @property Carbon|null $finalized_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class BranchClosureWorkflow extends BaseModel
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
        'status' => BranchClosureStatus::class,
    ];

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function isInitiated(): bool
    {
        return $this->status === BranchClosureStatus::Initiated;
    }

    public function isSettled(): bool
    {
        return $this->status === BranchClosureStatus::Settled;
    }

    public function isFinalized(): bool
    {
        return $this->status === BranchClosureStatus::Finalized;
    }

    public function markSettled(): void
    {
        $this->update([
            'status' => BranchClosureStatus::Settled->value,
            'settlement_at' => now(),
        ]);
    }

    public function markFinalized(): void
    {
        $this->update([
            'status' => BranchClosureStatus::Finalized->value,
            'finalized_at' => now(),
        ]);
    }
}
