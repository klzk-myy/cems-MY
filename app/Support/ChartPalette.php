<?php

namespace App\Support;

/**
 * Shared Chart.js doughnut palette — theme accent first, then a stable
 * rotation so a given rank always renders the same color.
 */
class ChartPalette
{
    protected const COLORS = ['#d4a843', '#3b82f6', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6', '#14b8a6', '#f97316'];

    /**
     * @return array<int, string>
     */
    public static function colors(int $count): array
    {
        return array_map(fn (int $i) => self::COLORS[$i % count(self::COLORS)], range(0, max(0, $count - 1)));
    }
}
