<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\CddLevel;
use App\Enums\IdType;
use App\Enums\RiskRating;
use App\Enums\TransactionStatus;
use App\Models\Compliance\CustomerBehavioralBaseline;
use App\Models\Compliance\CustomerRiskProfile;
use App\Services\System\EncryptionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Customer Model
 *
 * Represents customer information with encrypted identification data.
 * Supports risk assessment, PEP status tracking, and compliance monitoring.
 *
 * @property int $id
 * @property string $full_name
 * @property string $id_type 'MyKad', 'Passport', 'Others'
 * @property string $id_number_encrypted Encrypted ID/passport number
 * @property string $nationality
 * @property Carbon $date_of_birth
 * @property string|null $address
 * @property string $phone
 * @property string|null $email
 * @property bool $pep_status Politically Exposed Person
 * @property bool $sanction_hit Sanctions list match
 * @property int $risk_score 0-100
 * @property RiskRating $risk_rating
 * @property CddLevel $cdd_level 'Simplified', 'Standard', 'Enhanced'
 * @property bool $is_active
 * @property string|null $occupation
 * @property string|null $employer_name
 * @property string|null $employer_address
 * @property float|null $annual_volume_myr
 * @property string $customer_type 'individual', 'corporate'
 * @property string|null $id_number_hash Blind index for lookups
 * @property string|null $pep_type
 * @property Carbon|null $pep_role_ended_at
 * @property string|null $current_role_domain
 * @property string|null $former_pep_domain
 * @property bool $is_pep_associate
 * @property bool $is_frozen
 * @property string|null $freeze_reason
 * @property Carbon|null $frozen_at
 * @property bool $transactions_blocked
 * @property string|null $rejection_reason
 * @property string|null $closure_reason
 * @property Carbon|null $closed_at
 * @property Carbon|null $dormant_at
 * @property Carbon|null $sanctions_screened_at
 * @property Carbon|null $risk_assessed_at
 * @property Carbon|null $last_transaction_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read bool $is_pep
 * @property-read bool $is_sanctioned
 * @property-read string $cdd_level_label
 * @property-read string $risk_variant UI risk badge variant
 * @property-read string|null $id_number Decrypted ID number for display
 * @property-read Branch|null $branch Branch of the latest transaction — eager-load latestTransaction.branch before reading
 * @property-read string|null $transactions_sum_amount_myr Result of withSum('transactions', 'amount_myr')
 */
class Customer extends BaseModel
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'full_name',
        'id_type',
        'id_number_encrypted',
        'nationality',
        'date_of_birth',
        'address',
        'phone',
        'email',
        'pep_status',
        'pep_role_ended_at',
        'current_role_domain',
        'former_pep_domain',
        'is_pep_associate',
        'is_active',
        'occupation',
        'employer_name',
        'employer_address',
        'annual_volume_myr',
        'last_transaction_at',
        'customer_type',
        'pep_type',
        'sanctions_screened_at',
        'closure_reason',
    ];

    protected $hidden = [
        'id_number_encrypted',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date_of_birth' => 'date',
        'pep_status' => 'boolean',
        'pep_role_ended_at' => 'datetime',
        'sanction_hit' => 'boolean',
        'is_active' => 'boolean',
        'risk_score' => 'integer',
        'annual_volume_myr' => MoneyCast::class,
        'risk_assessed_at' => 'datetime',
        'last_transaction_at' => 'datetime',
        'cdd_level' => CddLevel::class,
        'risk_rating' => RiskRating::class,
        'is_frozen' => 'boolean',
        'frozen_at' => 'datetime',
        'transactions_blocked' => 'boolean',
        'id_type' => IdType::class,
        'customer_type' => 'string',
        'pep_type' => 'string',
        'sanctions_screened_at' => 'datetime',
        'freeze_reason' => 'string',
        'rejection_reason' => 'string',
        'closure_reason' => 'string',
        'closed_at' => 'datetime',
        'dormant_at' => 'datetime',
    ];

    /**
     * Boot the model and register event listeners.
     * Blind index (id_number_hash) is computed in the encryption service
     * where the plaintext ID number is available before encryption.
     */
    protected static function booted(): void
    {
        // No model-level event listeners needed - encryption and blind index
        // are handled in the service layer to ensure plaintext is available.
    }

    /**
     * Get all transactions for this customer.
     */
    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @return HasMany<ScreeningResult, $this>
     */
    public function screeningResults(): HasMany
    {
        return $this->hasMany(ScreeningResult::class);
    }

    /**
     * @return HasOne<Transaction, $this>
     */
    public function latestTransaction(): HasOne
    {
        return $this->hasOne(Transaction::class)->latestOfMany();
    }

    /**
     * Branch of the customer's latest transaction.
     *
     * Reads the already-loaded relations — eager-load
     * `latestTransaction.branch` on list endpoints to avoid a per-row
     * lazy-load cascade.
     */
    public function getBranchAttribute(): ?Branch
    {
        return $this->latestTransaction?->branch;
    }

    /**
     * @return Builder<Customer>
     */
    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->whereHas('transactions', function ($q) use ($branchId) {
            $q->where('branch_id', $branchId);
        });
    }

    /**
     * Get all notes associated with this customer.
     *
     * @return HasMany<CustomerNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(CustomerNote::class);
    }

    /**
     * Get all documents associated with this customer.
     *
     * @return HasMany<CustomerDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(CustomerDocument::class);
    }

    /**
     * Get risk assessment history for this customer.
     *
     * @return HasMany<CustomerRiskHistory, $this>
     */
    public function riskHistory(): HasMany
    {
        return $this->hasMany(CustomerRiskHistory::class);
    }

    /**
     * Get risk score snapshots for this customer.
     *
     * @return HasMany<RiskScoreSnapshot, $this>
     */
    public function riskScoreSnapshots(): HasMany
    {
        return $this->hasMany(RiskScoreSnapshot::class);
    }

    /**
     * Get the latest risk score snapshot for this customer.
     *
     * @return HasOne<RiskScoreSnapshot, $this>
     */
    public function latestRiskSnapshot(): HasOne
    {
        return $this->hasOne(RiskScoreSnapshot::class)->latestOfMany('snapshot_date');
    }

    /**
     * Customers whose LATEST risk snapshot is due for re-screening.
     *
     * Use this scope — never `whereHas('riskScoreSnapshots', needsRescreening)`:
     * an overdue historical row must not flag a customer whose latest snapshot
     * carries a future next_screening_date.
     *
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function scopeWhereLatestSnapshotNeedsRescreening(Builder $query): Builder
    {
        return $query->whereHas('latestRiskSnapshot', function ($snapshotQuery) {
            /** @var Builder<RiskScoreSnapshot> $snapshotQuery */
            $snapshotQuery->needsRescreening();
        });
    }

    /**
     * Get PEP relations for this customer.
     *
     * @return HasMany<CustomerRelation, $this>
     */
    public function pepRelations(): HasMany
    {
        return $this->hasMany(CustomerRelation::class, 'customer_id');
    }

    /**
     * Get associate relations where this customer is the related party.
     *
     * @return HasMany<CustomerRelation, $this>
     */
    public function associateRelations(): HasMany
    {
        return $this->hasMany(CustomerRelation::class, 'related_customer_id');
    }

    /**
     * Get behavioral baselines for this customer.
     *
     * @return HasMany<CustomerBehavioralBaseline, $this>
     */
    public function behavioralBaselines(): HasMany
    {
        return $this->hasMany(CustomerBehavioralBaseline::class);
    }

    /**
     * Get risk profiles for this customer.
     *
     * @return HasMany<CustomerRiskProfile, $this>
     */
    public function riskProfiles(): HasMany
    {
        return $this->hasMany(CustomerRiskProfile::class);
    }

    /**
     * Get PEP approval requests for this customer.
     *
     * @return HasMany<PepApprovalRequest, $this>
     */
    public function pepApprovalRequests(): HasMany
    {
        return $this->hasMany(PepApprovalRequest::class);
    }

    /**
     * Get sanctions analyses for this customer.
     *
     * @return HasMany<SanctionsAnalysis, $this>
     */
    public function sanctionsAnalyses(): HasMany
    {
        return $this->hasMany(SanctionsAnalysis::class);
    }

    /**
     * Get CDD level display label.
     */
    public function getCddLevelLabelAttribute(): string
    {
        return $this->cdd_level?->value ?? 'Simplified';
    }

    public function getIsPepAttribute(): bool
    {
        return (bool) $this->pep_status;
    }

    public function getIsSanctionedAttribute(): bool
    {
        return (bool) $this->sanction_hit;
    }

    /**
     * Check if the customer is higher risk (Medium or High risk rating).
     *
     * Used for PEP approval requirements per pd-00.md 14C.13.1(d).
     */
    public function isHigherRisk(): bool
    {
        return $this->risk_rating === RiskRating::Medium
            || $this->risk_rating === RiskRating::High;
    }

    public function freeze(string $reason): void
    {
        $this->is_frozen = true;
        $this->freeze_reason = $reason;
        $this->frozen_at = now();
        $this->save();
    }

    /**
     * Get the risk badge variant for UI display.
     */
    public function getRiskVariantAttribute(): string
    {
        $value = $this->risk_rating instanceof RiskRating
            ? $this->risk_rating->value
            : ($this->risk_rating ?? RiskRating::Medium->value);

        return match (strtolower($value)) {
            'high', 'critical' => 'danger',
            'medium' => 'warning',
            default => 'success',
        };
    }

    public function unfreeze(): void
    {
        $this->is_frozen = false;
        $this->freeze_reason = null;
        $this->frozen_at = null;
        $this->save();
    }

    /**
     * Transactions that block closure (awaiting approval or cancellation).
     *
     * @return Collection<int, Transaction>
     */
    public function openBlockingTransactions(): Collection
    {
        return $this->transactions()
            ->whereIn('status', [
                TransactionStatus::PendingApproval->value,
                TransactionStatus::PendingCancellation->value,
            ])
            ->get();
    }

    /**
     * Check if the customer can be closed (no pending-approval or
     * pending-cancellation transactions outstanding).
     */
    public function canBeClosed(): bool
    {
        return $this->openBlockingTransactions()->isEmpty();
    }

    public function reject(string $reason): void
    {
        $this->is_active = false;
        $this->rejection_reason = $reason;
        $this->save();
    }

    /**
     * Get the decrypted ID number for internal display.
     */
    public function getIdNumberAttribute(): ?string
    {
        if (empty($this->id_number_encrypted)) {
            return null;
        }

        try {
            // Accessors cannot receive DI; resolved intentionally.
            return app(EncryptionService::class)->decrypt($this->id_number_encrypted);
        } catch (\Exception $_e) {
            return null;
        }
    }

    /**
     * Legacy alias for the decrypted ID number.
     */
    public function getIcNumberAttribute(): ?string
    {
        return $this->id_number;
    }
}
