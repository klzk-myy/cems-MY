<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditTrail extends BaseModel
{
    protected $fillable = [
        'auditable_type',
        'auditable_id',
        'action',
        'user_id',
        'metadata',
        'ip_address',
    ];

    protected $casts = [
        'auditable_id' => 'integer',
        'user_id' => 'integer',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
