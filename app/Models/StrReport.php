<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\StrReportStatus;
use App\Models\Compliance\ComplianceCase;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * StrReport — Suspicious Transaction Report (pd-00 section 22).
 *
 * Drafted automatically when a compliance case is closed with an aggregate
 * flagged amount at or above the RM 50,000 threshold, or raised manually by
 * a compliance officer. Money is stored as decimal(18,4) MYR and handled as
 * BCMath-safe strings via MoneyCast.
 *
 * @property int $id
 * @property int|null $case_id
 * @property int $customer_id
 * @property string $trigger_amount_myr MYR aggregate (decimal(18,4) as string)
 * @property string $trigger_reason
 * @property StrReportStatus $status
 * @property string $trigger_amount_myr
 * @property string|null $bnm_reference
 * @property Carbon|null $submitted_at
 * @property Carbon|null $acknowledged_at
 * @property int $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
/**
 * @property int $id
 * @property string $str_no
 * @property int|null $branch_id
 * @property int $customer_id
 * @property int|null $alert_id
 * @property int|null $case_id
 * @property array|null $alert_ids
 * @property array $transaction_ids
 * @property string $reason
 * @property string|null $narrative
 * @property string|null $suspected_activity
 * @property int|null $confidence_score
 * @property int|null $converted_from_draft_id
 * @property array|null $supporting_documents
 * @property StrReportStatus $status
 * @property \Illuminate\Support\Carbon|null $submitted_at
 * @property string $trigger_amount_myr
 * @property string|null $bnm_reference
 * @property int $created_by
 * @property int|null $reviewed_by
 * @property int|null $approved_by
 * @property \Illuminate\Support\Carbon|null $suspicion_date
 * @property \Illuminate\Support\Carbon|null $filing_deadline
 * @property int $retry_count
 * @property string|null $last_error
 * @property \Illuminate\Support\Carbon|null $last_retry_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class StrReport extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'case_id',
        'customer_id',
        'trigger_amount_myr',
        'trigger_reason',
        'status',
        'bnm_reference',
        'submitted_at',
        'acknowledged_at',
        'created_by',
    ];

    protected $casts = [
        'trigger_amount_myr' => MoneyCast::class,
        'status' => StrReportStatus::class,
        'submitted_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<ComplianceCase, $this>
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(ComplianceCase::class, 'case_id');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Stable human reference used on listings and BNM-style exports.
     */
    public function reference(): string
    {
        return $this->bnm_reference ?? sprintf('STR-%s-%06d', ($this->created_at ?? now())->format('Y'), $this->id);
    }
}
