<?php

namespace App\Models\Compliance;

use App\Enums\ImportStatus;
use App\Enums\ImportTrigger;
use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $list_id
 * @property Carbon $imported_at
 * @property int $records_added
 * @property int $records_updated
 * @property int $records_deactivated
 * @property ImportStatus $status
 * @property string|null $error_message
 * @property ImportTrigger $triggered_by
 * @property int|null $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SanctionImportLog extends BaseModel
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
        'status' => ImportStatus::class,
        'triggered_by' => ImportTrigger::class,
    ];

    public function toSummaryArray(): array
    {
        return [
            'id' => $this->id,
            'list' => $this->sanctionList ? [
                'id' => $this->sanctionList->id,
                'name' => $this->sanctionList->name,
            ] : null,
            'imported_at' => $this->imported_at->toIso8601String(),
            'records_added' => $this->records_added,
            'records_updated' => $this->records_updated,
            'records_deactivated' => $this->records_deactivated,
            'status' => $this->status->value,
            'error_message' => $this->error_message,
            'triggered_by' => $this->triggered_by->value,
            'user_name' => $this->user?->username,
        ];
    }

    /**
     * @return BelongsTo<SanctionList, $this>
     */
    public function sanctionList(): BelongsTo
    {
        return $this->belongsTo(SanctionList::class, 'list_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
