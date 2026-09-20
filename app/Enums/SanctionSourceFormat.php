<?php

namespace App\Enums;

/**
 * File format of a sanctions list source (sanction_lists.source_format).
 */
enum SanctionSourceFormat: string
{
    case Xml = 'XML';
    case Csv = 'CSV';
    case Json = 'JSON';

    public function label(): string
    {
        return $this->value;
    }

    public function extension(): string
    {
        return strtolower($this->value);
    }
}
