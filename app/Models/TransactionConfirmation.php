<?php

namespace App\Models;

use App\Enums\TransactionConfirmationStatus;
use App\Models\Traits\BelongsToUser;
use App\Models\Traits\HasNotes;
use App\Models\Traits\HasStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionConfirmation extends BaseModel
{
    use BelongsToUser, HasFactory, HasNotes, HasStatus;

    protected string $statusColumn = 'status';

    protected $fillable = [
        'transaction_id',
        'confirmed_by',
        'confirmed_at',
        'confirmation_token',
        'expires_at',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
        'expires_at' => 'datetime',
        'status' => TransactionConfirmationStatus::class,
    ];

    protected function activeStatusValues(): array
    {
        return [TransactionConfirmationStatus::Confirmed->value];
    }

    protected function openStatusValues(): array
    {
        return [TransactionConfirmationStatus::Pending->value];
    }

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
        return $this->user();
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
        return $this->status === TransactionConfirmationStatus::Pending && ! $this->isExpired();
    }

    /**
     * Mark confirmation as confirmed.
     */
    public function markConfirmed(int $userId, ?string $notes = null): void
    {
        $this->update([
            'status' => TransactionConfirmationStatus::Confirmed->value,
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
            'status' => TransactionConfirmationStatus::Rejected->value,
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
        $this->update(['status' => TransactionConfirmationStatus::Expired->value]);
    }

    /**
     * Scope for pending confirmations.
     */
    public function scopePending($query)
    {
        return $query->where('status', TransactionConfirmationStatus::Pending->value)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }
}
