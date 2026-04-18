<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Department Model
 *
 * Represents a department within the organization for cost center grouping and reporting.
 *
 * @property int $id
 * @property string $code Unique department code
 * @property string $name Department name
 * @property string|null $description Department description
 * @property bool $is_active Whether the department is active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CostCenter> $costCenters
 */
class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the cost centers belonging to this department.
     */
    public function costCenters(): HasMany
    {
        return $this->hasMany(CostCenter::class);
    }
}
