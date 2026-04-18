<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionConfirmation extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'user_id',
        'confirmed_by',
        'confirmed_at',
        'status',
        'confirmation_token',
        'expires_at',
        'notes',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the transaction being confirmed.
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Get the user who requested the confirmation.
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the user who confirmed/rejected the confirmation.
     */
    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /**
     * Check if the confirmation has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Check if the confirmation is still pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending' && ! $this->isExpired();
    }

    /**
     * Mark confirmation as confirmed.
     */
    public function markConfirmed(int $userId, ?string $notes = null): void
    {
        $this->update([
            'status' => 'confirmed',
            'confirmed_by' => $userId,
            'confirmed_at' => now(),
            'notes' => $notes,
        ]);
    }

    /**
     * Mark confirmation as rejected.
     */
    public function markRejected(int $userId, ?string $reason = null): void
    {
        $this->update([
            'status' => 'rejected',
            'confirmed_by' => $userId,
            'confirmed_at' => now(),
            'notes' => $reason,
        ]);
    }

    /**
     * Mark confirmation as expired.
     */
    public function markExpired(): void
    {
        $this->update(['status' => 'expired']);
    }

    /**
     * Scope for pending confirmations.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }
}
