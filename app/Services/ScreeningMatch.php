<?php

namespace App\Services;

use App\Models\SanctionEntry;

class ScreeningMatch
{
    public function __construct(
        public readonly int $entryId,
        public readonly string $entityName,
        public readonly string $listName,
        public readonly string $listSource,
        public readonly float $matchScore,
        public readonly array $matchedFields,
        public readonly ?string $listingDate,
        public readonly ?string $dateOfBirth,
        public readonly ?string $nationality,
    ) {}

    public static function fromEntry(SanctionEntry $entry, float $score, array $fields = ['name']): self
    {
        return new self(
            entryId: $entry->id,
            entityName: $entry->entity_name,
            listName: $entry->sanctionList->name ?? 'Unknown',
            listSource: $entry->sanctionList->slug ?? 'unknown',
            matchScore: $score,
            matchedFields: $fields,
            listingDate: $entry->listing_date?->format('Y-m-d'),
            dateOfBirth: $entry->date_of_birth?->format('Y-m-d'),
            nationality: $entry->nationality,
        );
    }

    public function toArray(): array
    {
        return [
            'entry_id' => $this->entryId,
            'entity_name' => $this->entityName,
            'list_name' => $this->listName,
            'list_source' => $this->listSource,
            'match_score' => $this->matchScore,
            'matched_fields' => $this->matchedFields,
            'listing_date' => $this->listingDate,
            'date_of_birth' => $this->dateOfBirth,
            'nationality' => $this->nationality,
        ];
    }
}
