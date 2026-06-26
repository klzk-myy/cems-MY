<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class DbDate
{
    public static function monthColumn(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%m', {$column})"
            : "MONTH({$column})";
    }
}
