<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankReconciliation extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_code',
        'statement_date',
        'reference',
        'description',
        'debit',
        'credit',
        'status',
        'matched_to_journal_entry_id',
        'created_by',
        'matched_at',
        'notes',
        // Check-specific fields
        'check_number',
        'check_date',
        'check_status',
        'check_payee',
    ];

    protected $casts = [
        'statement_date' => 'date',
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
        'matched_at' => 'datetime',
        'check_date' => 'date',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_code', 'account_code');
    }

    public function matchedEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'matched_to_journal_entry_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeUnmatched($query)
    {
        return $query->where('status', 'unmatched');
    }

    public function scopeExceptions($query)
    {
        return $query->where('status', 'exception');
    }

    public function getAmount(): float
    {
        return (float) $this->debit - (float) $this->credit;
    }

    /**
     * Check if this is an outstanding check (issued but not yet presented)
     */
    public function isOutstandingCheck(): bool
    {
        return $this->check_number !== null
            && $this->check_status !== null
            && in_array($this->check_status, ['issued', 'presented']);
    }

    /**
     * Check if this check has cleared
     */
    public function isClearedCheck(): bool
    {
        return $this->check_status === 'cleared';
    }

    /**
     * Scope for outstanding checks
     */
    public function scopeOutstandingChecks($query)
    {
        return $query->whereNotNull('check_number')
            ->whereIn('check_status', ['issued', 'presented']);
    }

    /**
     * Scope for cleared checks
     */
    public function scopeClearedChecks($query)
    {
        return $query->where('check_status', 'cleared');
    }

    /**
     * Mark check as presented
     */
    public function markPresented(): void
    {
        $this->update(['check_status' => 'presented']);
    }

    /**
     * Mark check as cleared
     */
    public function markCleared(): void
    {
        $this->update(['check_status' => 'cleared']);
    }

    /**
     * Mark check as returned
     */
    public function markReturned(?string $reason = null): void
    {
        $this->update([
            'check_status' => 'returned',
            'notes' => $this->notes ? $this->notes.'; '.$reason : $reason,
        ]);
    }

    /**
     * Mark check as stopped
     */
    public function markStopped(?string $reason = null): void
    {
        $this->update([
            'check_status' => 'stopped',
            'notes' => $this->notes ? $this->notes.'; '.$reason : $reason,
        ]);
    }
}
