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
 * @property Carbon $business_date
 * @property array|null $checklist
 * @property Carbon|null $settlement_at
 * @property Carbon|null $finalized_at
 * @property Carbon|null $reopened_at
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
        'business_date',
        'checklist',
        'settlement_at',
        'finalized_at',
        'reopened_at',
    ];

    protected $casts = [
        'checklist' => 'array',
        'business_date' => 'date',
        'settlement_at' => 'datetime',
        'finalized_at' => 'datetime',
        'reopened_at' => 'datetime',
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

    /**
     * Whether the branch's books are frozen for a business date. The freeze
     * anchors on `business_date` (the day being closed), stamped at
     * initiation — so a workflow that settles/finalizes after midnight still
     * freezes the correct day, and nothing new can be booked onto a date
     * whose close is already in progress.
     *
     * Frozen while: Initiated, Settled, or Finalized for business_date >=
     * $date. A workflow reopened for corrections (Settled with reopened_at
     * set) deliberately un-freezes the date until it is finalized again.
     */
    public static function freezesDate(int $branchId, string $date): bool
    {
        return static::where('branch_id', $branchId)
            ->whereDate('business_date', '>=', $date)
            ->where(function ($query) {
                $query->whereIn('status', [
                    BranchClosureStatus::Initiated->value,
                    BranchClosureStatus::Finalized->value,
                ])->orWhere(function ($settled) {
                    $settled->where('status', BranchClosureStatus::Settled->value)
                        ->whereNull('reopened_at');
                });
            })
            ->exists();
    }

    /**
     * freezesDate() for callers inside a transaction: locks every workflow
     * row for the branch first so a concurrent settle→finalize cannot
     * commit between the check and the posting's commit. Finalize takes a
     * row lock before stamping its status, so this serializes the two.
     */
    public static function freezesDateForUpdate(int $branchId, string $date): bool
    {
        static::where('branch_id', $branchId)->lockForUpdate()->exists();

        return static::freezesDate($branchId, $date);
    }
}
