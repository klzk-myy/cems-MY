<?php

namespace App\Services\Contracts;

use App\Models\Customer;
use App\Models\Transaction;
use App\ValueObjects\ScreeningResponse;
use Illuminate\Support\Collection;

interface CustomerScreeningServiceInterface
{
    public function screenCustomer(Customer $customer): ScreeningResponse;

    public function screenName(string $name, ?string $dob = null, ?string $nationality = null, ?int $customerId = null, bool $persist = true): ScreeningResponse;

    public function screenTransaction(Transaction $transaction): ScreeningResponse;

    public function batchScreen(array $customerIds): Collection;

    public function getHistory(Customer $customer): Collection;

    public function handleConfirmedMatch(Customer $customer, string $listType): array;

    /**
     * @return array<string, mixed>
     */
    public function handleConfirmedAdverseMatch(Customer $customer, string $severity = 'medium'): array;

    public function getStatus(Customer $customer): array;

    public function levenshteinSimilarity(string $a, string $b): float;

    public function conductRelatedPartiesDueDiligence(Customer $customer): void;
}
