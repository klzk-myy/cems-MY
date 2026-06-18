<?php

namespace App\Services\Contracts;

use App\Models\JournalEntry;

interface AccountingServiceInterface
{
    public function createJournalEntry(
        array $lines,
        string $referenceType,
        ?int $referenceId = null,
        string $description = '',
        ?string $entryDate = null,
        ?int $createdBy = null
    ): JournalEntry;

    public function rejectEntry(
        JournalEntry $entry,
        ?int $rejectedBy = null,
        ?string $rejectionNotes = null
    ): JournalEntry;

    public function validateBalanced(array $lines): bool;

    public function reverseJournalEntry(
        JournalEntry $originalEntry,
        string $reason = '',
        ?int $reversedBy = null
    ): JournalEntry;

    public function getAccountBalance(string $accountCode, ?string $asOfDate = null): string;

    public function getAccountActivity(string $accountCode, string $startDate, string $endDate): string;
}
