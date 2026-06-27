<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\CddLevel;
use App\Enums\IdType;
use App\Enums\RiskRating;
use App\Models\Compliance\CustomerBehavioralBaseline;
use App\Models\Compliance\CustomerRiskProfile;
use App\Services\Customer\CustomerService;
use App\Services\System\EncryptionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

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
 * @property string $risk_rating 'Low', 'Medium', 'High'
 * @property string $cdd_level 'Simplified', 'Standard', 'Enhanced'
 * @property bool $is_active
 * @property string|null $occupation
 * @property string|null $employer_name
 * @property string|null $employer_address
 * @property float|null $annual_volume_estimate
 * @property Carbon|null $risk_assessed_at
 * @property Carbon|null $last_transaction_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Customer extends BaseModel
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'full_name',
        'id_type',
        'id_number_encrypted',
        'id_number_hash',
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
        'annual_volume_estimate',
        'last_transaction_at',
        'customer_type',
        'pep_type',
        'sanctions_screened_at',
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
        'annual_volume_estimate' => MoneyCast::class,
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
    ];

    /**
     * Get all transactions for this customer.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function latestTransaction(): HasOne
    {
        return $this->hasOne(Transaction::class)->latestOfMany();
    }

    public function getBranchAttribute(): ?Branch
    {
        return $this->latestTransaction?->branch;
    }

    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->whereHas('transactions', function ($q) use ($branchId) {
            $q->where('branch_id', $branchId);
        });
    }

    /**
     * Get all notes associated with this customer.
     */
    public function notes(): HasMany
    {
        return $this->hasMany(CustomerNote::class);
    }

    /**
     * Get all documents associated with this customer.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(CustomerDocument::class);
    }

    /**
     * Get risk assessment history for this customer.
     */
    public function riskHistory(): HasMany
    {
        return $this->hasMany(CustomerRiskHistory::class);
    }

    /**
     * Get risk score snapshots for this customer.
     */
    public function riskScoreSnapshots(): HasMany
    {
        return $this->hasMany(RiskScoreSnapshot::class);
    }

    /**
     * Get the latest risk score snapshot for this customer.
     */
    public function latestRiskSnapshot(): HasOne
    {
        return $this->hasOne(RiskScoreSnapshot::class)->latestOfMany('snapshot_date');
    }

    /**
     * Get PEP relations for this customer.
     */
    public function pepRelations(): HasMany
    {
        return $this->hasMany(CustomerRelation::class, 'customer_id');
    }

    /**
     * Get associate relations where this customer is the related party.
     */
    public function associateRelations(): HasMany
    {
        return $this->hasMany(CustomerRelation::class, 'related_customer_id');
    }

    /**
     * Get behavioral baselines for this customer.
     */
    public function behavioralBaselines(): HasMany
    {
        return $this->hasMany(CustomerBehavioralBaseline::class);
    }

    /**
     * Get risk profiles for this customer.
     */
    public function riskProfiles(): HasMany
    {
        return $this->hasMany(CustomerRiskProfile::class);
    }

    /**
     * Get PEP approval requests for this customer.
     */
    public function pepApprovalRequests(): HasMany
    {
        return $this->hasMany(PepApprovalRequest::class);
    }

    /**
     * Get sanctions analyses for this customer.
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
        return $this->cdd_level ?? 'Simplified';
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
        if ($this->risk_rating instanceof RiskRating) {
            return $this->risk_rating === RiskRating::Medium
                || $this->risk_rating === RiskRating::High;
        }

        // Handle string fallback
        return in_array($this->risk_rating, ['Medium', 'High']);
    }

    public function freeze(string $reason): void
    {
        $this->is_frozen = true;
        $this->freeze_reason = $reason;
        $this->frozen_at = now();
        $this->save();
    }

    public function unfreeze(): void
    {
        $this->is_frozen = false;
        $this->freeze_reason = null;
        $this->frozen_at = null;
        $this->save();
    }

    public function reject(string $reason): void
    {
        $this->is_active = false;
        $this->rejection_reason = $reason;
        $this->save();
    }

    /**
     * Get masked IC number for display (first 4 and last 4 digits).
     */
    public function getIcNumberAttribute(): ?string
    {
        if (! $this->id_number_encrypted) {
            return null;
        }

        try {
            $decrypted = app(EncryptionService::class)->decrypt($this->id_number_encrypted);
            if (strlen($decrypted) >= 8) {
                return substr($decrypted, 0, 4).'****'.substr($decrypted, -4);
            }

            return '****';
        } catch (\Exception $_e) {
            return '****';
        }
    }

    /**
     * Get masked ID number for PDPA-compliant display (first 4, last 4).
     *
     * Returns a format like "9001****1234" for MyKad numbers,
     * or "****" if decryption fails or the field is empty.
     */
    public function getIdNumberMaskedAttribute(): string
    {
        if (! $this->id_number_encrypted || empty($this->id_number_encrypted)) {
            return '****';
        }

        try {
            $decrypted = app(EncryptionService::class)->decrypt($this->id_number_encrypted);
            if (! $decrypted || strlen($decrypted) < 8) {
                return '****';
            }

            return substr($decrypted, 0, 4).'****'.substr($decrypted, -4);
        } catch (\Exception $_e) {
            return '****';
        }
    }

    /**
     * Boot the model and register hooks for blind index.
     */
    protected static function boot()
    {
        parent::boot();

        // When id_number (plaintext) is set, compute the blind index hash
        static::saving(function ($customer) {
            if ($customer->isDirty('id_number') && $customer->id_number) {
                $customer->id_number_hash = CustomerService::computeBlindIndex($customer->id_number);
            }
        });
    }
}
