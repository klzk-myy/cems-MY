<?php

namespace App\Models;

use App\Enums\EntityType;
use App\Enums\SanctionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SanctionEntry extends BaseModel
{
    use HasFactory, SoftDeletes;

    public $timestamps = false;

    protected $fillable = [
        'list_id',
        'list_source',
        'entity_name',
        'entity_type',
        'aliases',
        'nationality',
        'date_of_birth',
        'reference_number',
        'listing_date',
        'details',
        'address',
        'city',
        'country',
        'postal_code',
        'normalized_name',
        'soundex_code',
        'metaphone_code',
        'status',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'listing_date' => 'date',
        'entity_type' => EntityType::class,
        'status' => SanctionStatus::class,
    ];

    public static function buildFromValidated(array $data, array $normalized, bool $isUpdate = false): array
    {
        $payload = [
            'entity_name' => $data['entity_name'] ?? null,
            'entity_type' => $data['entity_type'] ?? null,
            'aliases' => $data['aliases'] ?? null,
            'nationality' => $data['nationality'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'reference_number' => $data['reference_number'] ?? null,
            'listing_date' => $data['listing_date'] ?? null,
            'details' => $data['details'] ?? null,
            'normalized_name' => $normalized['normalized_name'],
            'soundex_code' => $normalized['soundex_code'],
            'metaphone_code' => $normalized['metaphone_code'],
        ];

        if (! $isUpdate) {
            $payload['list_id'] = $data['list_id'];
            $payload['status'] = 'active';
        } else {
            $payload['list_source'] = $data['list_source'] ?? null;
            $payload['address'] = $data['address'] ?? null;
            $payload['city'] = $data['city'] ?? null;
            $payload['country'] = $data['country'] ?? null;
            $payload['postal_code'] = $data['postal_code'] ?? null;

            if (array_key_exists('status', $data)) {
                $payload['status'] = $data['status'];
            }
        }

        return $payload;
    }

    public function sanctionList(): BelongsTo
    {
        return $this->belongsTo(SanctionList::class, 'list_id');
    }

    public function getAliasesAttribute($value)
    {
        return json_decode($value, true) ?? [];
    }

    public function setAliasesAttribute($value)
    {
        $this->attributes['aliases'] = is_array($value) ? json_encode($value) : $value;
    }

    public function setDetailsAttribute($value)
    {
        $this->attributes['details'] = is_string($value) ? $value : (is_array($value) ? json_encode($value) : null);
    }
}
