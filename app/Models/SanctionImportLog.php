<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SanctionImportLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'list_id',
        'imported_at',
        'records_added',
        'records_updated',
        'records_deactivated',
        'status',
        'error_message',
        'triggered_by',
        'user_id',
    ];

    protected $casts = [
        'imported_at' => 'datetime',
        'records_added' => 'integer',
        'records_updated' => 'integer',
        'records_deactivated' => 'integer',
    ];

    public function sanctionList(): BelongsTo
    {
        return $this->belongsTo(SanctionList::class, 'list_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
