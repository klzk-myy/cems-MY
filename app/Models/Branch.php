<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

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
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Branch extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Branch type constants
     */
    public const TYPE_HEAD_OFFICE = 'head_office';

    public const TYPE_BRANCH = 'branch';

    public const TYPE_SUB_BRANCH = 'sub_branch';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
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
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include main branch (head office).
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeMain($query)
    {
        return $query->where('is_main', true);
    }

    /**
     * Scope a query to only include branches (not head offices).
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeBranches($query)
    {
        return $query->where('type', self::TYPE_BRANCH);
    }

    /**
     * Scope a query to only include head offices.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeHeadOffices($query)
    {
        return $query->where('type', self::TYPE_HEAD_OFFICE);
    }
}
