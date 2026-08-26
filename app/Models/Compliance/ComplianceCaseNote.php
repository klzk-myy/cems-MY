<?php

namespace App\Models\Compliance;

use App\Enums\CaseNoteType;
use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $case_id
 * @property int $author_id
 * @property CaseNoteType $note_type
 * @property string $content
 * @property bool $is_internal
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ComplianceCase $case
 * @property-read User $author
 */
class ComplianceCaseNote extends BaseModel
{
    use HasFactory, SoftDeletes;

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
    /**
     * @return BelongsTo<ComplianceCase, $this>
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(ComplianceCase::class, 'case_id');
    }

    /**
     * Get the author of this note.
     */
    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
