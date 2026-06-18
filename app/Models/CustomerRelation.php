<?php

namespace App\Models;

use App\Enums\IdType;
use App\Enums\RelationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerRelation extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'related_customer_id',
        'relation_type',
        'related_name',
        'id_type',
        'id_number_encrypted',
        'date_of_birth',
        'nationality',
        'address',
        'is_pep',
        'engagement_level',
        'engagement_notes',
        'engagement_assessed_at',
        'additional_info',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'is_pep' => 'boolean',
        'engagement_level' => 'string',
        'engagement_assessed_at' => 'datetime',
        'additional_info' => 'array',
        'relation_type' => RelationType::class,
        'id_type' => IdType::class,
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function relatedCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'related_customer_id');
    }

    public function isPepRelation(): bool
    {
        return $this->is_pep;
    }

    public function assessEngagement(string $level, ?string $notes = null): void
    {
        $this->update([
            'engagement_level' => $level,
            'engagement_notes' => $notes,
            'engagement_assessed_at' => now(),
        ]);
    }
}
