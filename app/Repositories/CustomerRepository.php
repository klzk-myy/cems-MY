<?php

namespace App\Repositories;

use App\Models\Customer;
use App\Services\Customer\CustomerService;
use Illuminate\Database\Eloquent\Collection;

class CustomerRepository
{
    public function findById(int $customerId): ?Customer
    {
        return Customer::find($customerId);
    }

    public function findByIdOrFail(int $customerId): Customer
    {
        return Customer::findOrFail($customerId);
    }

    public function findByIdNumber(string $idNumber): ?Customer
    {
        return Customer::where('id_number_hash', CustomerService::computeBlindIndex($idNumber))->first();
    }

    public function search(string $query): Collection
    {
        return Customer::where('full_name', 'like', "%{$query}%")
            ->orWhere('id_number_hash', 'like', "%{$query}%")
            ->get();
    }

    public function searchActive(string $query, int $limit = 10): Collection
    {
        $escapedQuery = str_replace(['%', '_'], ['\\%', '\\_'], $query);

        return Customer::where('full_name', 'like', "%{$escapedQuery}%")
            ->orWhere('id_number_encrypted', 'like', "%{$escapedQuery}%")
            ->where('is_active', true)
            ->limit($limit)
            ->get();
    }

    public function findActiveByIdNumberHash(string $idHash): ?Customer
    {
        return Customer::where('id_number_hash', $idHash)
            ->where('is_active', true)
            ->first();
    }

    public function getByIds(array $customerIds): Collection
    {
        return Customer::whereIn('id', $customerIds)->get();
    }

    public function getCustomersNeedingRescreening(): Collection
    {
        return Customer::where('risk_score', '>=', 60)
            ->orWhere('risk_assessed_at', '<', now()->subDays(30))
            ->get();
    }
}
