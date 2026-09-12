<?php

namespace App\Support;

/**
 * Shared LIKE-pattern escaper. Escapes the backslash first so it cannot
 * alter the escape character, then the % and _ wildcards. Pair the escaped
 * value with an explicit ESCAPE clause (bound as a parameter — a literal
 * ESCAPE '\' in SQL breaks MySQL because the backslash escapes the closing
 * quote) so it works on every driver; SQLite has no default LIKE escape.
 */
class LikeEscaper
{
    public static function escape(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
