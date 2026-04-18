<?php

namespace App\Models;

use App\Enums\RiskTrend;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskScoreSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'previous_score',
        'previous_rating',
        'overall_score',
        'overall_rating_label',
        'snapshot_date',
        'velocity_score',
        'structuring_score',
        'geographic_score',
        'amount_score',
        'trend',
        'factors',
        'next_screening_date',
    ];

    protected $casts = [
        'overall_score' => 'integer',
        'velocity_score' => 'integer',
        'structuring_score' => 'integer',
        'geographic_score' => 'integer',
        'amount_score' => 'integer',
        'trend' => RiskTrend::class,
        'factors' => 'array',
        'snapshot_date' => 'date',
        'next_screening_date' => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function scopeLatest($query)
    {
        return $query->orderByDesc('snapshot_date');
    }

    public function scopeNeedsRescreening($query)
    {
        return $query->where('next_screening_date', '<=', today());
    }

    public function isHighRisk(): bool
    {
        return $this->overall_score >= 60;
    }

    public function isCritical(): bool
    {
        return $this->overall_score >= 80;
    }

    public static function calculateTrend(array $snapshots): RiskTrend
    {
        if (count($snapshots) < 3) {
            return RiskTrend::Stable;
        }

        $recent = array_slice($snapshots, -3);
        $first = $recent[0]['overall_score'] ?? 0;
        $last = end($recent)['overall_score'] ?? 0;
        $diff = $last - $first;

        return match (true) {
            $diff > 10 => RiskTrend::Deteriorating,
            $diff < -10 => RiskTrend::Improving,
            default => RiskTrend::Stable,
        };
    }
}
