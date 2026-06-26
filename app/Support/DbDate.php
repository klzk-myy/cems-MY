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
}
