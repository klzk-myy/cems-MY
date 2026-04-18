<?php

namespace App\Enums;

/**
 * Case Note Type Enum
 *
 * Represents the different types of notes that can be added to a compliance case.
 */
enum CaseNoteType: string
{
    case Investigation = 'Investigation';
    case Update = 'Update';
    case Decision = 'Decision';
    case Escalation = 'Escalation';

    /**
     * Get a human-readable label for the note type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Investigation => 'Investigation',
            self::Update => 'Update',
            self::Decision => 'Decision',
            self::Escalation => 'Escalation',
        };
    }

    /**
     * Get the icon class for UI display.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Investigation => 'fa-search',
            self::Update => 'fa-edit',
            self::Decision => 'fa-gavel',
            self::Escalation => 'fa-arrow-alt-circle-up',
        };
    }
}
