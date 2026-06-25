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
