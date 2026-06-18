<?php

namespace App\Models;

use App\Enums\EntityType;
use App\Enums\SanctionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SanctionEntry extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $with = [];

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

    public function getDetailsAttribute($value)
    {
        return $value;
    }

    public function setDetailsAttribute($value)
    {
        $this->attributes['details'] = is_string($value) ? $value : null;
    }

    public function setEntityTypeAttribute($value): void
    {
        if ($value instanceof EntityType) {
            $value = $value->value;
        }

        $this->attributes['entity_type'] = is_string($value) ? ucfirst($value) : $value;
    }
}
