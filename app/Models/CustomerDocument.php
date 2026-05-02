<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * CustomerDocument Model
 *
 * Represents KYC documents uploaded for customers.
 * Document types include MyKad front/back, Passport, Proof of Address.
 *
 * @property int $id
 * @property int $customer_id
 * @property string $document_type 'MyKad', 'Passport', 'Proof_of_Address', 'Others'
 * @property string $file_path
 * @property string|null $file_hash
 * @property int|null $file_size
 * @property bool $encrypted
 * @property int $uploaded_by
 * @property Carbon|null $verified_by
 * @property Carbon|null $verified_at
 * @property Carbon|null $expiry_date
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class CustomerDocument extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'customer_id',
        'document_type',
        'file_path',
        'file_hash',
        'file_size',
        'encrypted',
        'uploaded_by',
        'verified_by',
        'verified_at',
        'expiry_date',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'encrypted' => 'boolean',
        'verified_at' => 'datetime',
        'expiry_date' => 'date',
        'file_size' => 'integer',
    ];

    /**
     * Document types enum
     */
    public const DOCUMENT_TYPES = [
        'MyKad_Front' => 'MyKad (Front)',
        'MyKad_Back' => 'MyKad (Back)',
        'Passport' => 'Passport',
        'Proof_of_Address' => 'Proof of Address',
        'Others' => 'Other Document',
    ];

    /**
     * Get the customer that owns the document.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the user who uploaded the document.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get the user who verified the document.
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Scope a query to only include verified documents.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeVerified($query)
    {
        return $query->whereNotNull('verified_by')->whereNotNull('verified_at');
    }

    /**
     * Scope a query to only include unverified documents.
     *
     * @param  Builder  $query
     * @return \Illuminate\Database\Eloquent\Eloquent\Builder
     */
    public function scopeUnverified($query)
    {
        return $query->whereNull('verified_by')->whereNull('verified_at');
    }

    /**
     * Scope a query to only include expired documents.
     *
     * @param  Builder  $query
     * @return \Illuminate\Database\Eloquent\Eloquent\Builder
     */
    public function scopeExpired($query)
    {
        return $query->whereNotNull('expiry_date')->where('expiry_date', '<', now());
    }

    /**
     * Scope a query to only include documents expiring soon.
     *
     * @param  Builder  $query
     * @return \Illuminate\Database\Eloquent\Eloquent\Builder
     */
    public function scopeExpiringSoon($query)
    {
        return $query->whereNotNull('expiry_date')
            ->where('expiry_date', '>', now())
            ->where('expiry_date', '<=', now()->addDays(30));
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Check if the document is verified.
     */
    public function isVerified(): bool
    {
        return $this->verified_by !== null && $this->verified_at !== null;
    }
}
