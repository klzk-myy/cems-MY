<?php

namespace App\Models\Compliance;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ComplianceCaseLink extends BaseModel
{
    use HasFactory, SoftDeletes;

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
