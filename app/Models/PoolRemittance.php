<?php

namespace App\Models;

use App\Enums\PoolRemittanceStatus;
use Database\Factories\PoolRemittanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A cash remittance between a trading branch and the head-office branch.
 *
 * Remittance is a two-step transfer: initiation debits the sender's pool
 * and parks the value in the inter-branch clearing account (2300);
 * acknowledgement credits the receiver's pool and clears 2300. Exactly
 * one side of every remittance is the head-office branch — branches move
 * stock between each other via StockTransfer, cash only via HQ.
 *
 * @property int $id
 * @property string $remittance_number
 * @property int $from_branch_id
 * @property int $to_branch_id
 * @property string $currency_code
 * @property string $amount
 * @property PoolRemittanceStatus $status
 * @property int $initiated_by
 * @property Carbon|null $initiated_at
 * @property int|null $acknowledged_by
 * @property Carbon|null $acknowledged_at
 * @property int|null $cancelled_by
 * @property Carbon|null $cancelled_at
 * @property string|null $notes
 * @property int|null $out_journal_entry_id
 * @property int|null $ack_journal_entry_id
 * @property int|null $cancel_journal_entry_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Branch $fromBranch
 * @property-read Branch $toBranch
 * @property-read User $initiator
 * @property-read User|null $acknowledger
 * @property-read User|null $canceller
 */
class PoolRemittance extends Model
{
    /** @use HasFactory<PoolRemittanceFactory> */
    use HasFactory;

    protected $fillable = [
        'remittance_number',
        'from_branch_id',
        'to_branch_id',
        'currency_code',
        'amount',
        'status',
        'initiated_by',
        'initiated_at',
        'acknowledged_by',
        'acknowledged_at',
        'cancelled_by',
        'cancelled_at',
        'notes',
        'out_journal_entry_id',
        'ack_journal_entry_id',
        'cancel_journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'status' => PoolRemittanceStatus::class,
            'initiated_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isPending(): bool
    {
        return $this->status->isPending();
    }

    /**
     * Generate the next remittance number for today.
     *
     * Same pessimistic-read convention as StockTransfer::generateTransferNumber:
     * the caller MUST create the remittance inside its own transaction so the
     * lock taken here is held until commit, serialising concurrent generators.
     */
    public static function generateRemittanceNumber(): string
    {
        $prefix = 'REM-';
        $date = now()->format('Ymd');

        $latest = self::where('remittance_number', 'like', "{$prefix}{$date}-%")
            ->orderByDesc('remittance_number')
            ->lockForUpdate()
            ->value('remittance_number');

        $sequence = $latest !== null
            ? (int) substr((string) $latest, strrpos((string) $latest, '-') + 1) + 1
            : 1;

        return "{$prefix}{$date}-".str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
