<?php

namespace App\Models;

use App\Enums\BranchType;
use App\Models\Traits\HasCodeAndName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Branch Model
 *
 * Represents a branch or head office in the CEMS-MY multi-branch system.
 * Supports hierarchical branch structure (parent/child relationships).
 *
 * @property int $id
 * @property string $code Branch code (HQ, BR001, etc.)
 * @property string $name Branch name
 * @property string $type Branch type (head_office, branch, sub_branch)
 * @property string|null $address
 * @property string|null $city
 * @property string|null $state
 * @property string|null $postal_code
 * @property string $country
 * @property string|null $phone
 * @property string|null $email
 * @property bool $is_active
 * @property bool $is_main
 * @property int|null $parent_id
 * @property string $petty_cash_myr MYR petty-cash float for branch expenses
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Branch extends BaseModel
{
    use HasCodeAndName, HasFactory, SoftDeletes;

    /**
     * Branch type constants — backed by the BranchType enum; kept as
     * constants so existing call sites compile unchanged.
     */
    public const TYPE_HEAD_OFFICE = BranchType::HeadOffice->value;

    public const TYPE_BRANCH = BranchType::Branch->value;

    public const TYPE_SUB_BRANCH = BranchType::SubBranch->value;

    /**
     * Width of the branches.code column - tombstoned codes must still fit.
     */
    protected const CODE_COLUMN_LENGTH = 20;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'type',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'phone',
        'email',
        'is_active',
        'is_main',
        'parent_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'is_main' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Branch $branch) {
            // code is UNIQUE but rows are soft-deleted: without a tombstone a
            // deleted branch would permanently burn its code for reuse.
            if ($branch->isForceDeleting()) {
                return;
            }

            $suffix = '_del_'.($branch->id ?? Str::random(6));
            $base = substr($branch->code ?? '', 0, max(0, self::CODE_COLUMN_LENGTH - strlen($suffix)));

            $branch->code = $base.$suffix;
            // Quiet save so the delete flow is not re-triggered.
            $branch->saveQuietly();
        });
    }

    /**
     * Get all users belonging to this branch.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get all counters (tills) for this branch.
     */
    public function counters(): HasMany
    {
        return $this->hasMany(Counter::class);
    }

    /**
     * Get all transactions for this branch.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get all journal entries for this branch.
     */
    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    /**
     * Get all currency positions for this branch.
     */
    public function currencyPositions(): HasMany
    {
        return $this->hasMany(CurrencyPosition::class);
    }

    /**
     * Get all till balances for this branch.
     */
    public function tillBalances(): HasMany
    {
        return $this->hasMany(TillBalance::class);
    }

    /**
     * Get all counter sessions for this branch through counters.
     */
    public function counterSessions(): HasManyThrough
    {
        return $this->hasManyThrough(CounterSession::class, Counter::class);
    }

    /**
     * Get the parent branch (if this is a sub-branch or child branch).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'parent_id');
    }

    /**
     * Get the child branches (sub-branches or child branches).
     */
    public function children(): HasMany
    {
        return $this->hasMany(Branch::class, 'parent_id');
    }

    /**
     * Scope a query to only include active branches.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include main branch (head office).
     */
    public function scopeMain(Builder $query): Builder
    {
        return $query->where('is_main', true);
    }

    /**
     * Scope a query to only include branches (not head offices).
     */
    public function scopeBranches(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_BRANCH);
    }

    /**
     * Scope a query to only include head offices.
     */
    public function scopeHeadOffices(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_HEAD_OFFICE);
    }

    /**
     * Head-office branches are non-trading: no transactions, no counters,
     * no foreign-currency stock. They hold an MYR expense float only.
     */
    public function isHeadOffice(): bool
    {
        return $this->type === self::TYPE_HEAD_OFFICE;
    }

    /**
     * Whether this branch may process exchange transactions and hold stock.
     */
    public function canTrade(): bool
    {
        return ! $this->isHeadOffice();
    }

    /**
     * Get closure workflows for this branch.
     */
    public function closureWorkflows(): HasMany
    {
        return $this->hasMany(BranchClosureWorkflow::class);
    }

    /**
     * Get exchange rates for this branch.
     */
    public function exchangeRates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class);
    }

    /**
     * Get exchange rate histories for this branch.
     */
    public function exchangeRateHistories(): HasMany
    {
        return $this->hasMany(ExchangeRateHistory::class);
    }
}
