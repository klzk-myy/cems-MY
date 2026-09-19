<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Branch petty-cash expense.
 *
 * Each expense posts a balanced branch journal (Dr expense account,
 * Cr petty cash) and decrements the branch petty-cash float.
 *
 * @property int $id
 * @property int|null $branch_id
 * @property string $account_code
 * @property string $category
 * @property string $description
 * @property string $amount_myr
 * @property string $expense_date
 * @property int|null $journal_entry_id
 * @property int $created_by
 */
class Expense extends BaseModel
{
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'account_code',
        'category',
        'description',
        'amount_myr',
        'expense_date',
        'journal_entry_id',
        'created_by',
    ];

    protected $casts = [
        'amount_myr' => MoneyCast::class,
        'expense_date' => 'date',
    ];

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
