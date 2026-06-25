<?php

namespace App\Helpers;

class LabelHelper
{
    /**
     * Get the label from a status, handling both enum and string types.
     */
    public static function getStatusLabel($status, string $default = ''): string
    {
        if ($status === null) {
            return $default;
        }

        if (is_object($status) && method_exists($status, 'label')) {
            return $status->label();
        }

        if (is_object($status) && method_exists($status, 'value')) {
            return (string) $status->value;
        }

        if (is_string($status)) {
            return $status;
        }

        return $default;
    }

    /**
     * Get the label from a type/enum, handling both enum and string types.
     */
    public static function getTypeLabel($type, string $default = ''): string
    {
        if (is_object($type) && method_exists($type, 'label')) {
            return $type->label();
        }

        if (is_string($type)) {
            return $type;
        }

        return $default;
    }
}
