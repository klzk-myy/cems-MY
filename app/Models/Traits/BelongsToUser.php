<?php

namespace App\Models\Traits;

use App\Models\User;
/**
 * @property int $user_id
 */
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToUser
{
    public function initializeBelongsToUser(): void
    {
        $this->mergeFillable(['user_id']);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
