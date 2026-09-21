<?php

namespace App\Models;

use App\Enums\StockTransferStatus;
use App\Enums\StockTransferType;
use App\Exceptions\Domain\TransactionCreationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $transfer_number
 * @property string $type 'Standard', 'Emergency', 'Scheduled', 'Return'
 * @property StockTransferStatus $status
 * @property int|null $source_branch_id
 * @property int|null $destination_branch_id
 * @property string|null $source_branch_name Display snapshot — identity lives on the FK columns
 * @property string|null $destination_branch_name Display snapshot — identity lives on the FK columns
 * @property int $requested_by
 * @property Carbon|null $requested_at
 * @property int|null $branch_manager_approved_by
 * @property Carbon|null $branch_manager_approved_at
 * @property int|null $hq_approved_by
 * @property Carbon|null $hq_approved_at
 * @property Carbon|null $dispatched_at
 * @property Carbon|null $completed_at
 * @property string|null $notes
 * @property string|null $cancellation_reason
 * @property string $total_value_myr
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class StockTransfer extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'transfer_number',
        'type',
        'status',
        'source_branch_id',
        'destination_branch_id',
        'source_branch_name',
        'destination_branch_name',
        'requested_by',
        'requested_at',
        'branch_manager_approved_by',
        'branch_manager_approved_at',
        'hq_approved_by',
        'hq_approved_at',
        'dispatched_at',
        'completed_at',
        'notes',
        'cancellation_reason',
        'total_value_myr',
    ];

    protected $casts = [
        'status' => StockTransferStatus::class,
        'requested_at' => 'datetime',
        'branch_manager_approved_at' => 'datetime',
        'hq_approved_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'completed_at' => 'datetime',
        'total_value_myr' => 'decimal:2',
    ];

    /**
     * Transfer type constants — backed by the StockTransferType enum; kept
     * as constants so existing call sites compile unchanged.
     */
    public const TYPE_STANDARD = StockTransferType::Standard->value;

    public const TYPE_EMERGENCY = StockTransferType::Emergency->value;

    public const TYPE_SCHEDULED = StockTransferType::Scheduled->value;

    public const TYPE_RETURN = StockTransferType::Return->value;

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function sourceBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'source_branch_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function destinationBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'destination_branch_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function branchManagerApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'branch_manager_approved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function hqApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hq_approved_by');
    }

    /**
     * @return HasMany<StockTransferItem, $this>
     */
    /**
     * @return HasMany<StockTransferItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', StockTransferStatus::Requested->value);
    }

    public function scopeInTransit(Builder $query): Builder
    {
        return $query->where('status', StockTransferStatus::InTransit->value);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', StockTransferStatus::Completed->value);
    }

    public function isPending(): bool
    {
        return $this->status === StockTransferStatus::Requested;
    }

    public function isInTransit(): bool
    {
        return $this->status === StockTransferStatus::InTransit;
    }

    public function isCompleted(): bool
    {
        return $this->status === StockTransferStatus::Completed;
    }

    public function canApproveBranchManager(): bool
    {
        return $this->status === StockTransferStatus::Requested;
    }

    public function canDispatch(): bool
    {
        // Maker/taker: taker approval (BranchManagerApproved) is sufficient.
        // HqApproved is accepted for transfers created before the HQ step
        // was removed.
        return in_array($this->status, [StockTransferStatus::BranchManagerApproved, StockTransferStatus::HqApproved], true);
    }

    public function canReceive(): bool
    {
        return $this->status === StockTransferStatus::InTransit;
    }

    public function canComplete(): bool
    {
        return in_array($this->status, [StockTransferStatus::InTransit, StockTransferStatus::PartiallyReceived, StockTransferStatus::Received]);
    }

    public function canCancel(): bool
    {
        return ! $this->isCompleted() && $this->status !== StockTransferStatus::Cancelled;
    }

    public function approveByBranchManager(User $user): void
    {
        $this->update([
            'status' => StockTransferStatus::BranchManagerApproved,
            'branch_manager_approved_by' => $user->id,
            'branch_manager_approved_at' => now(),
        ]);
    }

    public function approveByHQ(User $user): void
    {
        $this->update([
            'status' => StockTransferStatus::HqApproved,
            'hq_approved_by' => $user->id,
            'hq_approved_at' => now(),
        ]);
    }

    public function dispatch(): void
    {
        $this->update([
            'status' => StockTransferStatus::InTransit,
            'dispatched_at' => now(),
        ]);
    }

    public function complete(): void
    {
        $this->update([
            'status' => StockTransferStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    public function cancel(string $reason): void
    {
        $this->update([
            'status' => StockTransferStatus::Cancelled,
            'cancellation_reason' => $reason,
        ]);
    }

    /**
     * Generate the next transfer number for today.
     *
     * The sequence is derived from MAX(transfer_number), including soft-deleted
     * rows so numbers are never reused, under a pessimistic read. The caller
     * MUST create the StockTransfer inside its own database transaction: the
     * lock taken here is held until that transaction commits - i.e. after the
     * caller's INSERT - which serialises concurrent generators and prevents two
     * requests from deriving (and inserting) the same number. The previous
     * implementation committed its own transaction before the caller inserted,
     * releasing those locks early and colliding on the unique transfer_number
     * index.
     *
     * @throws TransactionCreationException If no unique number could be produced
     */
    public static function generateTransferNumber(): string
    {
        $prefix = 'TRF-';
        $date = now()->format('Ymd');

        $maxRetries = 3;
        $attempt = 0;

        while (true) {
            $attempt++;

            // Highest existing number today (withTrashed so soft-deleted rows
            // reserve their sequence). lockForUpdate() joins the caller's open
            // transaction and holds the range lock until it commits.
            $latest = self::withTrashed()
                ->where('transfer_number', 'like', "{$prefix}{$date}-%")
                ->orderByDesc('transfer_number')
                ->lockForUpdate()
                ->value('transfer_number');

            $lastSequence = $latest !== null
                ? (int) substr((string) $latest, strrpos((string) $latest, '-') + 1)
                : 0;

            // Bump the sequence on each retry so repeated calls never repeat a number.
            $sequence = str_pad((string) ($lastSequence + $attempt), 4, '0', STR_PAD_LEFT);
            $transferNumber = "{$prefix}{$date}-{$sequence}";

            // Double-check uniqueness (covers callers outside any transaction,
            // where the lock above cannot be held across their insert).
            if (! self::withTrashed()->where('transfer_number', $transferNumber)->exists()) {
                return $transferNumber;
            }

            if ($attempt >= $maxRetries) {
                throw new TransactionCreationException("Failed to generate unique transfer number after {$maxRetries} attempts");
            }
        }
    }
}
