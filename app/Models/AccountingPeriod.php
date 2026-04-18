<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * AccountingPeriod model representing a fiscal/accounting period.
 *
 * This model manages accounting periods used for organizing financial transactions.
 * Each period has a defined date range and can be open or closed for new entries.
 * When closed, no new journal entries can be posted to the period.
 *
 * @property int $id
 * @property string $period_code Unique identifier code for the period (e.g., "2024-01")
 * @property \Carbon\Carbon $start_date Start date of the accounting period
 * @property \Carbon\Carbon $end_date End date of the accounting period
 * @property string $period_type Type of period (e.g., monthly, quarterly, yearly)
 * @property string $status Current status: 'open' or 'closed'
 * @property \Carbon\Carbon|null $closed_at Timestamp when the period was closed
 * @property int|null $closed_by ID of the user who closed the period
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|JournalEntry[] $journalEntries Journal entries posted in this period
 * @property-read User|null $closedBy User who closed this period
 */
class AccountingPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'period_code',
        'start_date',
        'end_date',
        'period_type',
        'status',
        'closed_at',
        'closed_by',
        'fiscal_year_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'closed_at' => 'datetime',
    ];

    /**
     * Journal entries associated with this accounting period.
     */
    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    /**
     * User who closed this accounting period.
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Check if the accounting period is open.
     *
     * @return bool True if the period status is 'open'
     */
    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Check if the accounting period is closed.
     *
     * @return bool True if the period status is 'closed'
     */
    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /**
     * Scope a query to only include open periods.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    /**
     * Scope a query to only include the current period (where today falls within the period dates).
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCurrent($query)
    {
        return $query->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now());
    }

    /**
     * Scope a query to find periods containing a specific date.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $date  Date string to search for (YYYY-MM-DD format)
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForDate($query, string $date)
    {
        return $query->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date);
    }
}
