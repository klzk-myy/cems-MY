<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $currency_code
 * @property string $till_id
 * @property string $old_rate
 * @property string $new_rate
 * @property string $position_amount
 * @property string $gain_loss_amount
 * @property Carbon $revaluation_date
 * @property int $posted_by
 * @property Carbon $posted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class RevaluationEntry extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'currency_code',
        'till_id',
        'old_rate',
        'new_rate',
        'position_amount',
        'gain_loss_amount',
        'revaluation_date',
        'posted_by',
        'posted_at',
    ];

    protected $casts = [
        'old_rate' => MoneyCast::class.':6',
        'new_rate' => MoneyCast::class.':6',
        'position_amount' => MoneyCast::class,
        'gain_loss_amount' => MoneyCast::class,
        'revaluation_date' => 'date',
        'posted_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}
