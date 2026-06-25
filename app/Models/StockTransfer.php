<?php

namespace App\Models;

use App\Enums\StockTransferStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class StockTransfer extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $with = ['items'];

    protected $fillable = [
        'transfer_number',
        'type',
        'status',
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

    public const TYPE_STANDARD = 'Standard';

    public const TYPE_EMERGENCY = 'Emergency';

    public const TYPE_SCHEDULED = 'Scheduled';

    public const TYPE_RETURN = 'Return';

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function branchManagerApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'branch_manager_approved_by');
    }

    public function hqApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hq_approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', StockTransferStatus::Requested);
    }

    public function scopeInTransit(Builder $query): Builder
    {
        return $query->where('status', StockTransferStatus::InTransit);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', StockTransferStatus::Completed);
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

    public function canApproveHq(): bool
    {
        return $this->status === StockTransferStatus::BranchManagerApproved;
    }

    public function canDispatch(): bool
    {
        return $this->status === StockTransferStatus::HqApproved;
    }

    public function canReceive(): bool
    {
        return $this->status === StockTransferStatus::InTransit;
    }

    public function canComplete(): bool
    {
        return in_array($this->status, [StockTransferStatus::InTransit, StockTransferStatus::PartiallyReceived]);
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

    public static function generateTransferNumber(): string
    {
        $prefix = 'TRF-';
        $date = now()->format('Ymd');

        $maxRetries = 3;
        $attempt = 0;

        while (true) {
            $attempt++;
            DB::beginTransaction();

            try {
                // Lock today's records to prevent concurrent generation of same sequence
                $count = self::whereDate('created_at', today())->lockForUpdate()->count();
                $sequence = str_pad($count + 1, 4, '0', STR_PAD_LEFT);
                $transferNumber = "{$prefix}{$date}-{$sequence}";

                // Double-check for uniqueness
                if (self::where('transfer_number', $transferNumber)->exists()) {
                    if ($attempt >= $maxRetries) {
                        throw new \RuntimeException("Failed to generate unique transfer number after {$maxRetries} attempts");
                    }
                    DB::rollBack();

                    continue;
                }

                DB::commit();

                return $transferNumber;
            } catch (\Exception $e) {
                DB::rollBack();
                if ($attempt >= $maxRetries) {
                    throw $e;
                }
            }
        }
    }
}
