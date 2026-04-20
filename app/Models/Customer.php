<?php

namespace App\Models;

use App\Enums\CddLevel;
use App\Services\CustomerService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
class Customer extends Model
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
        'sanction_hit',
        'is_pep_associate',
        'risk_score',
        'risk_rating',
        'cdd_level',
        'is_active',
        'occupation',
        'employer_name',
        'employer_address',
        'annual_volume_estimate',
        'risk_assessed_at',
        'last_transaction_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date_of_birth' => 'date',
        'pep_status' => 'boolean',
        'sanction_hit' => 'boolean',
        'is_active' => 'boolean',
        'risk_score' => 'integer',
        'annual_volume_estimate' => 'decimal:4',
        'risk_assessed_at' => 'datetime',
        'last_transaction_at' => 'datetime',
        'cdd_level' => CddLevel::class,
    ];

    /**
     * Get all transactions for this customer.
     *
     * @return HasMany
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get all documents associated with this customer.
     *
     * @return HasMany
     */
    public function documents()
    {
        return $this->hasMany(CustomerDocument::class);
    }

    /**
     * Get risk assessment history for this customer.
     *
     * @return HasMany
     */
    public function riskHistory()
    {
        return $this->hasMany(CustomerRiskHistory::class);
    }

    /**
     * Get risk score snapshots for this customer.
     *
     * @return HasMany
     */
    public function riskScoreSnapshots()
    {
        return $this->hasMany(RiskScoreSnapshot::class);
    }

    /**
     * Get the latest risk score snapshot for this customer.
     *
     * @return HasOne
     */
    public function latestRiskSnapshot()
    {
        return $this->hasOne(RiskScoreSnapshot::class)->latest('snapshot_date');
    }

    /**
     * Get the latest risk level attribute.
     * Returns the risk level from the latest snapshot or the customer's risk_rating.
     */
    public function getRiskLevelAttribute(): string
    {
        return $this->latestRiskSnapshot?->overall_rating_label ?? $this->risk_rating ?? 'Unknown';
    }

    /**
     * Get PEP relations for this customer.
     *
     * @return HasMany
     */
    public function pepRelations()
    {
        return $this->hasMany(CustomerRelation::class, 'customer_id');
    }

    /**
     * Get associate relations where this customer is the related party.
     *
     * @return HasMany
     */
    public function associateRelations()
    {
        return $this->hasMany(CustomerRelation::class, 'related_customer_id');
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
