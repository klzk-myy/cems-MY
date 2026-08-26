<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * Records every password hash a user has used so recently used
 * passwords can be rejected by App\Rules\PasswordNotRecentlyUsed.
 *
 * @property int $user_id
 * @property string $password
 */
class PasswordHistory extends Model
{
    /**
     * History rows are immutable and only carry a creation timestamp.
     */
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'password',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $history): void {
            $history->created_at ??= now();
        });
    }

    /**
     * Persist a superseded password hash for a user.
     */
    public static function record(int $userId, string $passwordHash): self
    {
        return static::create([
            'user_id' => $userId,
            'password' => $passwordHash,
        ]);
    }

    /**
     * The most recent recorded hashes for a user, newest first.
     *
     * @return Collection<int, string>
     */
    public static function recentHashesFor(User $user, int $depth): Collection
    {
        return static::query()
            ->where('user_id', $user->getKey())
            ->orderByDesc('id')
            ->limit($depth)
            ->pluck('password');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
