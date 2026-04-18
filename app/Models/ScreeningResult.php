<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScreeningResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'transaction_id',
        'screened_name',
        'sanction_entry_id',
        'match_type',
        'match_score',
        'action_taken',
        'result',
        'matched_fields',
    ];

    protected $casts = [
        'match_score' => 'float',
        'matched_fields' => 'array',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function sanctionEntry(): BelongsTo
    {
        return $this->belongsTo(SanctionEntry::class);
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
}
