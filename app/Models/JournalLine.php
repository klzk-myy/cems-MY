<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Bases\AccountingModel;
use App\Services\System\MathService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Journal Line model representing individual line items within a journal entry.
 *
 * Each journal line represents a debit or credit transaction to a specific account
 * within a complete double-entry bookkeeping journal entry. A valid journal entry
 * must have at least two lines where total debits equal total credits.
 *
 * @property int $id Unique identifier for the journal line
 * @property int $journal_entry_id Reference to the parent journal entry
 * @property string $account_code Account code from chart of accounts
 * @property string|null $debit Debit amount (nullable, 4 decimal places)
 * @property string|null $credit Credit amount (nullable, 4 decimal places)
 * @property string|null $description Optional description for this line
 * @property Carbon $created_at Timestamp when created
 * @property Carbon $updated_at Timestamp when last updated
 * @property-read JournalEntry $journalEntry The parent journal entry
 * @property-read ChartOfAccount $account The chart of account for this line
 */
class JournalLine extends AccountingModel
{
    use HasFactory;

    protected $with = ['account'];

    protected $fillable = [
        'journal_entry_id',
        'account_code',
        'debit',
        'credit',
        'description',
    ];

    protected $casts = [
        'debit' => MoneyCast::class,
        'credit' => MoneyCast::class,
    ];

    /**
     * Get the parent journal entry that this line belongs to.
     *
     * @return BelongsTo<JournalEntry, self>
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * Get the chart of account associated with this journal line.
     *
     * @return BelongsTo<ChartOfAccount, self>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_code', 'account_code');
    }

    /**
     * Determine if this journal line represents a debit transaction.
     *
     * Uses MathService to compare the debit amount with zero.
     *
     * @return bool True if debit amount is greater than zero
     */
    public function isDebit(): bool
    {
        return app(MathService::class)->compare((string) $this->debit, '0') > 0;
    }

    /**
     * Determine if this journal line represents a credit transaction.
     *
     * Uses MathService to compare the credit amount with zero.
     *
     * @return bool True if credit amount is greater than zero
     */
    public function isCredit(): bool
    {
        return app(MathService::class)->compare((string) $this->credit, '0') > 0;
    }

    /**
     * Get the monetary amount of this line, preferring debit over credit.
     *
     * Returns the debit amount if it exists (> 0), otherwise returns the credit amount.
     * This is useful for displaying the line amount without knowing if it's a debit or credit.
     *
     * @return string The amount as a string (debit preferred)
     */
    public function getAmount(): string
    {
        $mathService = app(MathService::class);
        if ($mathService->compare((string) $this->debit, '0') > 0) {
            return (string) $this->debit;
        }

        return (string) $this->credit;
    }
}
