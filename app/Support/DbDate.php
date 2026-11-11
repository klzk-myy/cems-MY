<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class DbDate
{
    public static function monthColumn(string $column): string
    {
        $grammar = DB::connection()->getQueryGrammar();
        $wrapped = $grammar->wrap($column);

        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%m', {$wrapped})"
            : "MONTH({$wrapped})";
    }

    /**
     * Year-month bucket expression ('YYYY-MM') for GROUP BY, driver-aware.
     */
    public static function monthBucket(string $column): string
    {
        $grammar = DB::connection()->getQueryGrammar();
        $wrapped = $grammar->wrap($column);

        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$wrapped})"
            : "DATE_FORMAT({$wrapped}, '%Y-%m')";
    }

    /**
     * Signed whole-second difference (endColumn − startColumn), driver-aware.
     */
    public static function secondsBetween(string $startColumn, string $endColumn): string
    {
        $grammar = DB::connection()->getQueryGrammar();
        $start = $grammar->wrap($startColumn);
        $end = $grammar->wrap($endColumn);

        return DB::connection()->getDriverName() === 'sqlite'
            ? "(strftime('%s', {$end}) - strftime('%s', {$start}))"
            : "TIMESTAMPDIFF(SECOND, {$start}, {$end})";
    }
}
