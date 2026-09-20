<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\BankReconciliationStatus;
use App\Enums\CheckStatus;
use App\Support\BcmathHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $account_code
 * @property Carbon $statement_date
 * @property string|null $reference
 * @property string|null $check_number
 * @property Carbon|null $check_date
 * @property CheckStatus|null $check_status 'issued', 'presented', 'cleared', 'returned', 'stopped'
 * @property string|null $check_payee
 * @property string $description
 * @property string $debit
 * @property string $credit
 * @property BankReconciliationStatus $status 'unmatched', 'matched', 'exception'
 * @property int|null $matched_to_journal_entry_id
 * @property int $created_by
 * @property Carbon|null $matched_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class BankReconciliation extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'account_code',
        'statement_date',
        'reference',
        'description',
        'debit',
        'credit',
        'status',
        'matched_to_journal_entry_id',
        'created_by',
        'matched_at',
        'notes',
        // Check-specific fields
        'check_number',
        'check_date',
        'check_status',
        'check_payee',
    ];

    protected $casts = [
        'statement_date' => 'date',
        'debit' => MoneyCast::class,
        'credit' => MoneyCast::class,
        'matched_at' => 'datetime',
        'check_date' => 'date',
        'status' => BankReconciliationStatus::class,
        'check_status' => CheckStatus::class,
    ];

    /**
     * @return BelongsTo<ChartOfAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_code', 'account_code');
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function matchedEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'matched_to_journal_entry_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeUnmatched($query)
    {
        return $query->where('status', BankReconciliationStatus::Unmatched->value);
    }

    public function scopeExceptions($query)
    {
        return $query->where('status', BankReconciliationStatus::Exception->value);
    }

    public function getAmount(): string
    {

        return BcmathHelper::subtract((string) $this->debit, (string) $this->credit);
    }

    /**
     * Check if this is an outstanding check (issued but not yet presented)
     */
    public function isOutstandingCheck(): bool
    {
        return $this->check_number !== null
            && $this->check_status !== null
            && in_array($this->check_status, [CheckStatus::Issued, CheckStatus::Presented]);
    }

    /**
     * Check if this check has cleared
     */
    public function isClearedCheck(): bool
    {
        return $this->check_status === CheckStatus::Cleared;
    }

    /**
     * Scope for outstanding checks
     */
    public function scopeOutstandingChecks($query)
    {
        return $query->whereNotNull('check_number')
            ->whereIn('check_status', [CheckStatus::Issued->value, CheckStatus::Presented->value]);
    }

    /**
     * Scope for cleared checks
     */
    public function scopeClearedChecks($query)
    {
        return $query->where('check_status', CheckStatus::Cleared->value);
    }

    /**
     * Mark check as presented
     */
    public function markPresented(): void
    {
        $this->update(['check_status' => CheckStatus::Presented->value]);
    }

    /**
     * Mark check as cleared
     */
    public function markCleared(): void
    {
        $this->update(['check_status' => CheckStatus::Cleared->value]);
    }

    /**
     * Mark check as returned
     */
    public function markReturned(?string $reason = null): void
    {
        $this->update([
            'check_status' => CheckStatus::Returned->value,
            'notes' => $this->notes ? $this->notes.'; '.$reason : $reason,
        ]);
    }

    /**
     * Mark check as stopped
     */
    public function markStopped(?string $reason = null): void
    {
        $this->update([
            'check_status' => CheckStatus::Stopped->value,
            'notes' => $this->notes ? $this->notes.'; '.$reason : $reason,
        ]);
    }

    /**
     * Mark this reconciliation record as manually matched to a journal entry.
     */
    public function markMatched(int $journalEntryId): void
    {
        $this->update([
            'status' => BankReconciliationStatus::Matched->value,
            'matched_to_journal_entry_id' => $journalEntryId,
            'matched_at' => now(),
        ]);
    }
}
