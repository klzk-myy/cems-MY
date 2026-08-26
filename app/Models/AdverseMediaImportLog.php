<?php

namespace App\Models;

use Illuminate\Support\Carbon;

/**
 * Import run log for adverse-media:import, mirroring SanctionImportLog.
 *
 * @property int $id
 * @property string|null $imported_file
 * @property Carbon $imported_at
 * @property int $records_added
 * @property int $records_updated
 * @property int $records_deactivated
 * @property int $records_skipped
 * @property string $status 'success', 'partial', 'failed'
 * @property string|null $error_message
 * @property string $triggered_by 'scheduled', 'manual'
 * @property int|null $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AdverseMediaImportLog extends BaseModel
{
    protected $fillable = [
        'imported_file',
        'imported_at',
        'records_added',
        'records_updated',
        'records_deactivated',
        'records_skipped',
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
        'records_skipped' => 'integer',
    ];
}
