<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Fiscal Year Model
 *
 * Represents a fiscal year for annual financial reporting and year-end closing.
 *
 * @property int $id
 * @property string $year_code Unique fiscal year code (e.g., FY2026)
 * @property Carbon $start_date Fiscal year start date
 * @property Carbon $end_date Fiscal year end date
 * @property string $status Open, Closed, or Archived
 * @property int|null $closed_by User who closed the fiscal year
 * @property Carbon|null $closed_at When the fiscal year was closed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $closedBy
 * @property-read Collection<int, AccountingPeriod> $periods
 */
class FiscalYear extends Model
{
    use HasFactory;

    protected $fillable = [
        'year_code',
        'start_date',
        'end_date',
        'status',
        'closed_by',
        'closed_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'closed_at' => 'datetime',
    ];

    /**
     * Get the user who closed this fiscal year.
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Get the accounting periods belonging to this fiscal year.
     */
    public function periods(): HasMany
    {
        return $this->hasMany(AccountingPeriod::class);
    }

    /**
     * Check if this fiscal year is open.
     */
    public function isOpen(): bool
    {
        return $this->status === 'Open';
    }

    /**
     * Check if this fiscal year is closed.
     */
    public function isClosed(): bool
    {
        return $this->status === 'Closed';
    }
}
