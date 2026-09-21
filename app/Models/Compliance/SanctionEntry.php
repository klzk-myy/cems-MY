<?php

namespace App\Models\Compliance;

use App\Enums\EntityType;
use App\Enums\SanctionStatus;
use App\Models\BaseModel;
use App\Support\LikeEscaper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $list_id
 * @property string|null $list_source
 * @property string $entity_name
 * @property string|null $normalized_name
 * @property string|null $soundex_code
 * @property string|null $metaphone_code
 * @property SanctionStatus $status
 * @property EntityType $entity_type
 * @property array|null $aliases
 * @property string|null $nationality
 * @property Carbon|null $date_of_birth
 * @property string|null $reference_number
 * @property Carbon|null $listing_date
 * @property string|null $address
 * @property string|null $city
 * @property string|null $country
 * @property string|null $postal_code
 * @property array|null $details
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 *
 * Runtime screening-context attributes stamped onto the instance by the
 * screening pipeline before a match notification is dispatched (not columns):
 * @property int|null $customer_id
 * @property int|null $transaction_id
 * @property string|null $screened_name
 * @property string|null $matched_name
 * @property string|\BackedEnum|null $match_type
 * @property float|null $match_score 0.0-1.0 similarity score
 * @property bool|null $is_whitelisted
 */
class SanctionEntry extends BaseModel
{
    use HasFactory, SoftDeletes;

    public $timestamps = true;

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
        'details' => 'array',
        'entity_type' => EntityType::class,
        'status' => SanctionStatus::class,
    ];

    public static function buildForCreate(array $data, array $normalized): array
    {
        return [
            'list_id' => $data['list_id'],
            'entity_name' => $data['entity_name'] ?? null,
            'entity_type' => $data['entity_type'] ?? null,
            'aliases' => static::parseAliases($data['aliases'] ?? null),
            'nationality' => $data['nationality'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'reference_number' => $data['reference_number'] ?? null,
            'listing_date' => $data['listing_date'] ?? null,
            'details' => $data['details'] ?? null,
            'normalized_name' => $normalized['normalized_name'],
            'soundex_code' => $normalized['soundex_code'],
            'metaphone_code' => $normalized['metaphone_code'],
            'status' => SanctionStatus::Active->value,
        ];
    }

    public static function buildForUpdate(array $data, array $normalized): array
    {
        $payload = [
            'entity_name' => $data['entity_name'] ?? null,
            'entity_type' => $data['entity_type'] ?? null,
            'aliases' => static::parseAliases($data['aliases'] ?? null),
            'nationality' => $data['nationality'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'reference_number' => $data['reference_number'] ?? null,
            'listing_date' => $data['listing_date'] ?? null,
            'details' => $data['details'] ?? null,
            'list_source' => $data['list_source'] ?? null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'country' => $data['country'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'normalized_name' => $normalized['normalized_name'],
            'soundex_code' => $normalized['soundex_code'],
            'metaphone_code' => $normalized['metaphone_code'],
        ];

        if (array_key_exists('status', $data)) {
            $payload['status'] = $data['status'];
        }

        return $payload;
    }

    public function toEntrySummaryArray(): array
    {
        return [
            'id' => $this->id,
            'entity_name' => $this->entity_name,
            'entity_type' => $this->entity_type,
            'list' => $this->sanctionList ? [
                'id' => $this->sanctionList->id,
                'name' => $this->sanctionList->name,
            ] : null,
            'list_source' => $this->list_source,
            'nationality' => $this->nationality,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'reference_number' => $this->reference_number,
            'status' => $this->status,
            'listing_date' => $this->listing_date?->format('Y-m-d'),
        ];
    }

    public static function parseAliases(?string $aliases): ?array
    {
        if ($aliases === null || trim($aliases) === '') {
            return null;
        }

        return array_filter(array_map('trim', explode("\n", $aliases)));
    }

    /**
     * Shared filter chain for the web and API sanction-entry listings —
     * both controllers must filter identically (status/list/LIKE search).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeFiltered($query, ?string $status, ?int $listId, ?string $search)
    {
        return $query
            ->with('sanctionList')
            ->when($listId, fn ($q, $id) => $q->where('list_id', $id))
            ->when($search, function ($q, $search) {
                // Escape LIKE wildcards so literal % and _ in the query match
                // literally. The escape char is bound as a parameter — a literal
                // ESCAPE '\' broke MySQL because the backslash escaped the
                // closing quote (SQL syntax error 1064).
                $pattern = '%'.LikeEscaper::escape($search).'%';

                return $q->whereRaw('entity_name LIKE ? ESCAPE ?', [$pattern, '\\']);
            })
            ->when(
                $status !== null && $status !== 'all',
                fn ($q) => $q->where('status', $status)
            )
            ->orderBy('entity_name');
    }

    /**
     * @return BelongsTo<SanctionList, $this>
     */
    public function sanctionList(): BelongsTo
    {
        return $this->belongsTo(SanctionList::class, 'list_id');
    }

    /**
     * Canonical FK naming alias — the column is `list_id` (predates the
     * {singular}_id convention); consumers should read `sanction_list_id`.
     */
    public function getSanctionListIdAttribute(): ?int
    {
        return $this->list_id;
    }

    public function getAliasesAttribute($value)
    {
        return $value === null ? [] : (json_decode($value, true) ?? []);
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
