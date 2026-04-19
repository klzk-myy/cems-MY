<?php

namespace App\Models;

use App\Enums\JournalEntryStatus;
use App\Services\MathService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Journal Entry Model
 *
 * Represents a journal entry in the accounting system. Journal entries are the foundation
 * of double-entry bookkeeping, recording financial transactions with balanced debit and credit amounts.
 * Each entry contains multiple journal lines and can be posted to the general ledger.
 *
 * @property int $id
 * @property string|null $entry_number Unique entry number (JE-YYYYMM-XXXX)
 * @property int $period_id
 * @property Carbon $entry_date
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property string|null $description
 * @property string $status Draft, Pending, Posted, Reversed, Rejected
 * @property int|null $posted_by
 * @property Carbon|null $posted_at
 * @property int|null $reversed_by
 * @property Carbon|null $reversed_at
 * @property int|null $created_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property string|null $approval_notes
 * @property int|null $cost_center_id
 * @property int|null $department_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, JournalLine> $lines
 * @property-read User|null $postedBy
 * @property-read User|null $reversedBy
 * @property-read User|null $creator
 * @property-read User|null $approver
 * @property-read Collection<int, AccountLedger> $ledgerEntries
 * @property-read AccountingPeriod $period
 * @property-read CostCenter|null $costCenter
 * @property-read Department|null $department
 */
class JournalEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'period_id',
        'entry_date',
        'reference_type',
        'reference_id',
        'description',
        'status',
        'posted_by',
        'posted_at',
        'reversed_by',
        'reversed_at',
        'entry_number',
        'created_by',
        'approved_by',
        'approved_at',
        'approval_notes',
        'cost_center_id',
        'department_id',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'posted_at' => 'datetime',
        'reversed_at' => 'datetime',
        'approved_at' => 'datetime',
        'status' => JournalEntryStatus::class,
    ];

    /**
     * Get the journal lines associated with this entry.
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class)->orderBy('id');
    }

    /**
     * Get the user who posted this journal entry.
     */
    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /**
     * Get the user who reversed this journal entry.
     */
    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    /**
     * Get the user who created this journal entry.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who approved this journal entry.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the cost center associated with this journal entry.
     */
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    /**
     * Get the department associated with this journal entry.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the ledger entries associated with this journal entry.
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(AccountLedger::class);
    }

    /**
     * Check if this journal entry has been posted.
     */
    public function isPosted(): bool
    {
        return $this->status === JournalEntryStatus::Posted;
    }

    /**
     * Check if this journal entry is in draft status.
     */
    public function isDraft(): bool
    {
        return $this->status === JournalEntryStatus::Draft;
    }

    /**
     * Check if this journal entry is pending approval.
     */
    public function isPending(): bool
    {
        return $this->status === JournalEntryStatus::Pending;
    }

    /**
     * Check if this journal entry has been rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === JournalEntryStatus::Rejected;
    }

    /**
     * Check if this journal entry has been reversed.
     */
    public function isReversed(): bool
    {
        return $this->status === JournalEntryStatus::Reversed;
    }

    /**
     * Get the total amount of debits for this journal entry.
     */
    public function getTotalDebits(): string
    {
        $mathService = new MathService;
        $total = '0';

        // Use $this->lines which respects eager loading - no new query if already loaded
        foreach ($this->lines as $line) {
            $total = $mathService->add($total, (string) $line->debit);
        }

        return $total;
    }

    /**
     * Get the total amount of credits for this journal entry.
     */
    public function getTotalCredits(): string
    {
        $mathService = new MathService;
        $total = '0';

        // Use $this->lines which respects eager loading - no new query if already loaded
        foreach ($this->lines as $line) {
            $total = $mathService->add($total, (string) $line->credit);
        }

        return $total;
    }

    /**
     * Check if the journal entry is balanced (total debits equal total credits).
     */
    public function isBalanced(): bool
    {
        $mathService = new MathService;

        return $mathService->compare($this->getTotalDebits(), $this->getTotalCredits()) === 0;
    }

    /**
     * Get the accounting period this journal entry belongs to.
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class);
    }
}
