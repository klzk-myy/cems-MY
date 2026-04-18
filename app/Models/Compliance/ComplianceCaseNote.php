<?php

namespace App\Models\Compliance;

use App\Enums\CaseNoteType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplianceCaseNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'case_id',
        'author_id',
        'note_type',
        'content',
        'is_internal',
    ];

    protected $casts = [
        'note_type' => CaseNoteType::class,
        'is_internal' => 'boolean',
    ];

    /**
     * Get the case this note belongs to.
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(ComplianceCase::class, 'case_id');
    }

    /**
     * Get the author of this note.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'author_id');
    }
}
