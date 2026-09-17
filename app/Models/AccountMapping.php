<?php

namespace App\Models;

use Database\Factories\AccountMappingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Account Mapping
 *
 * Maps a business posting key (e.g. 'cash.myr', 'inventory.default',
 * 'inventory.USD') to a chart_of_accounts row. The table is the runtime
 * source of truth for which GL account a posting path debits/credits;
 * AccountMappingKey provides the seeded defaults and fallback values.
 *
 * @property int $id
 * @property string $key Mapping key ('cash.myr', 'inventory.default', 'cash.USD', ...)
 * @property string $account_code FK to chart_of_accounts
 * @property string|null $description Human-readable "used by" note
 * @property int|null $updated_by Last editor
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ChartOfAccount|null $account
 * @property-read User|null $editor
 */
class AccountMapping extends BaseModel
{
    /** @use HasFactory<AccountMappingFactory> */
    use HasFactory;

    protected $fillable = [
        'key',
        'account_code',
        'description',
        'updated_by',
    ];

    /** @return BelongsTo<ChartOfAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_code', 'account_code');
    }

    /** @return BelongsTo<User, $this> */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
