<?php

namespace App\Helpers;

class LabelHelper
{
    /**
     * Get the label from a status, handling both enum and string types.
     */
    public static function getStatusLabel(mixed $value, string $default = 'Unknown'): string
    {
        return self::getLabel($value, $default, ['getStatusLabel', 'getStatusDisplayName', 'label', 'name']);
    }

    /**
     * Get the label from a type/enum, handling both enum and string types.
     */
    public static function getTypeLabel(mixed $value, string $default = 'Unknown'): string
    {
        return self::getLabel($value, $default, ['getTypeLabel', 'getTypeDisplayName', 'label', 'name']);
    }

    /**
     * Resolve a label from an enum, object, or scalar value.
     */
    private static function getLabel(mixed $value, string $default, array $methodPreference): string
    {
        if ($value === null) {
            return $default;
        }

        foreach ($methodPreference as $method) {
            if (method_exists($value, $method)) {
                return (string) $value->{$method}();
            }
        }

        if (is_object($value) && enum_exists(get_class($value))) {
            return $value->name ?? $default;
        }

        return (string) $value;
    }
}
