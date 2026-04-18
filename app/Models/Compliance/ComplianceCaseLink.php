<?php

namespace App\Models\Compliance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ComplianceCaseLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'case_id',
        'linked_type',
        'linked_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Get the case this link belongs to.
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(ComplianceCase::class, 'case_id');
    }

    /**
     * Get the linked subject (polymorphic).
     */
    public function subject(): MorphTo
    {
        return $this->morphTo('subject', 'linked_type', 'linked_id');
    }
}
