<?php

namespace App\Models;

use App\Enums\MatchType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScreeningResult extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'transaction_id',
        'screened_name',
        'sanction_entry_id',
        'adverse_media_entry_id',
        'source',
        'match_type',
        'match_score',
        'action_taken',
        'result',
        'matched_fields',
        'disposition',
        'disposition_reason',
        'dispositioned_by',
        'dispositioned_at',
    ];

    protected $casts = [
        'match_score' => 'float',
        'matched_fields' => 'array',
        'match_type' => MatchType::class,
        'dispositioned_at' => 'datetime',
    ];

    public function isAdverseMedia(): bool
    {
        return $this->source === 'adverse_media';
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * @return BelongsTo<SanctionEntry, $this>
     */
    public function sanctionEntry(): BelongsTo
    {
        return $this->belongsTo(SanctionEntry::class);
    }

    /**
     * @return BelongsTo<AdverseMediaEntry, $this>
     */
    public function adverseMediaEntry(): BelongsTo
    {
        return $this->belongsTo(AdverseMediaEntry::class);
    }

    public function isBlocked(): bool
    {
        return $this->result === 'block';
    }

    public function isFlagged(): bool
    {
        return $this->result === 'flag';
    }

    public function isClear(): bool
    {
        return $this->result === 'clear';
    }

    public function isPending(): bool
    {
        return $this->disposition === null;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithHits(Builder $query): Builder
    {
        return $query->whereIn('result', ['flag', 'block']);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('disposition');
    }

    public function markDispositioned(string $disposition, string $reason, int $userId): void
    {
        $this->update([
            'disposition' => $disposition,
            'disposition_reason' => $reason,
            'dispositioned_by' => $userId,
            'dispositioned_at' => now(),
        ]);
    }
}
