<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $code_hash
 * @property bool $used
 * @property Carbon|null $used_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MfaRecoveryCode extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'code_hash',
        'used',
        'used_at',
    ];

    protected $hidden = [
        'code_hash',
    ];

    protected $casts = [
        'used' => 'boolean',
        'used_at' => 'datetime',
    ];

    /**
     * Get the user that owns the recovery code.
     */
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the code is available for use.
     */
    public function isAvailable(): bool
    {
        return ! $this->used;
    }
}
