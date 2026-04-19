<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Cost Center Model
 *
 * Represents a cost center for tracking expenses and revenues by department or project.
 *
 * @property int $id
 * @property string $code Unique cost center code
 * @property string $name Cost center name
 * @property string|null $description Cost center description
 * @property bool $is_active Whether the cost center is active
 * @property int|null $department_id Associated department
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Department|null $department
 */
class CostCenter extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
        'department_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the department this cost center belongs to.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
