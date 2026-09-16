<?php

namespace App\Services\Transaction\DTOs;

use App\Models\Counter;
use App\Models\Currency;
use App\Models\TransactionImport;
use App\Models\User;

/**
 * Per-import working state for TransactionImportService.
 *
 * Previously these lived on the service as mutable properties, which leaked
 * caches and error/success counts across imports when the same service
 * instance processed more than one file. A fresh ImportContext is created
 * per process() call so state can never bleed between imports.
 */
final class ImportContext
{
    /** @var array<string, Currency|null> Currency existence cache, keyed by code. */
    private array $currencyCache = [];

    /** @var array<string, Counter|null> Counter lookup cache, keyed by till id. */
    private array $counterCache = [];

    /** @var array<int, array{row: int, data: array<int, string|null>, error: string}> */
    private array $errors = [];

    private int $successCount = 0;

    public function __construct(
        public readonly TransactionImport $import,
        public readonly User $importUser,
    ) {}

    public function cachedCurrency(string $code): ?Currency
    {
        if (! array_key_exists($code, $this->currencyCache)) {
            $this->currencyCache[$code] = Currency::where('code', $code)->first();
        }

        return $this->currencyCache[$code];
    }

    public function cachedCounter(string $tillKey): ?Counter
    {
        if (! array_key_exists($tillKey, $this->counterCache)) {
            $this->counterCache[$tillKey] = Counter::where('code', $tillKey)
                ->orWhere('id', $tillKey)
                ->first();
        }

        return $this->counterCache[$tillKey];
    }

    public function recordSuccess(): void
    {
        $this->successCount++;
    }

    /**
     * @param  array<int, string|null>  $row
     */
    public function recordError(int $rowNumber, array $row, string $error): void
    {
        $this->errors[] = [
            'row' => $rowNumber,
            'data' => $row,
            'error' => $error,
        ];
    }

    /**
     * @return array<int, array{row: int, data: array<int, string|null>, error: string}>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function successCount(): int
    {
        return $this->successCount;
    }
}
