<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Bases\AccountingModel;
use App\Support\BcmathHelper;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Account Ledger Model
 *
 * Represents a ledger entry in the accounting system.
 * Tracks all debit and credit transactions for specific accounts
 * with running balance calculations.
 *
 * @property int $id The unique identifier for the ledger entry
 * @property string $account_code The account code associated with this ledger entry
 * @property Carbon $entry_date The date of the ledger entry
 * @property int $journal_entry_id The associated journal entry ID
 * @property string|null $debit The debit amount for this entry
 * @property string|null $credit The credit amount for this entry
 * @property string|null $running_balance The running balance after this entry
 * @property Carbon|null $created_at Timestamp when the record was created
 * @property Carbon|null $updated_at Timestamp when the record was last updated
 * @property-read ChartOfAccount $account The chart of account associated with this ledger entry
 * @property-read JournalEntry $journalEntry The journal entry associated with this ledger entry
 */
class AccountLedger extends AccountingModel
{
    use HasFactory;

    protected $table = 'account_ledger';

    protected $fillable = [
        'account_code',
        'branch_id',
        'entry_date',
        'journal_entry_id',
        'debit',
        'credit',
        'running_balance',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'debit' => MoneyCast::class,
        'credit' => MoneyCast::class,
        'running_balance' => MoneyCast::class,
    ];

    /**
     * Get the chart of account associated with this ledger entry.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_code', 'account_code');
    }

    /**
     * Get the journal entry associated with this ledger entry.
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * Calculate the net amount for this ledger entry.
     *
     * Computes the difference between debit and credit amounts
     * using high-precision arithmetic via BcmathHelper.
     *
     * @return string The net amount (debit - credit) as a string for precision
     */
    public function getNetAmount(): string
    {
        return BcmathHelper::subtract((string) $this->debit, (string) $this->credit);
    }

    /**
     * Scope rows to a date range (start inclusive, end inclusive).
     */
    public function scopeEntryDateBetween($query, ?string $startDate, ?string $endDate)
    {
        if ($startDate !== null) {
            $query->whereDate('entry_date', '>=', $startDate);
        }

        if ($endDate !== null) {
            $query->whereDate('entry_date', '<=', $endDate);
        }

        return $query;
    }

    /**
     * Scope rows to a branch, when provided.
     * Nullable: skip the filter when branchId is null.
     */
    public function scopeWhereBranch($query, ?int $branchId)
    {
        return $branchId === null ? $query : $query->where('branch_id', $branchId);
    }
}
