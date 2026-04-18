<?php

namespace App\Models;

use App\Services\MathService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
 * @property \Carbon\Carbon $entry_date The date of the ledger entry
 * @property int $journal_entry_id The associated journal entry ID
 * @property string|null $debit The debit amount for this entry
 * @property string|null $credit The credit amount for this entry
 * @property string|null $running_balance The running balance after this entry
 * @property \Carbon\Carbon|null $created_at Timestamp when the record was created
 * @property \Carbon\Carbon|null $updated_at Timestamp when the record was last updated
 * @property-read \App\Models\ChartOfAccount $account The chart of account associated with this ledger entry
 * @property-read \App\Models\JournalEntry $journalEntry The journal entry associated with this ledger entry
 */
class AccountLedger extends Model
{
    use HasFactory;

    protected $table = 'account_ledger';

    protected $fillable = [
        'account_code',
        'entry_date',
        'journal_entry_id',
        'branch_id',
        'debit',
        'credit',
        'running_balance',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'debit' => 'decimal:4',
        'credit' => 'decimal:4',
        'running_balance' => 'decimal:4',
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
     * using high-precision arithmetic via MathService.
     *
     * @return string The net amount (debit - credit) as a string for precision
     */
    public function getNetAmount(): string
    {
        $mathService = new MathService;

        return $mathService->subtract((string) $this->debit, (string) $this->credit);
    }
}
