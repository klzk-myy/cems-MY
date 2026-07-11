<?php

namespace App\Services\Contracts;

use App\Models\Customer;
use App\Services\Customer\CustomerActionResult;

interface CustomerServiceInterface
{
    public function createCustomerAction(array $data, int $createdBy): CustomerActionResult;

    public function updateCustomerAction(Customer $customer, array $data, int $updatedBy): CustomerActionResult;

    public function createCustomer(array $data, int $userId): Customer;

    public function updateCustomer(Customer $customer, array $data, int $userId): Customer;

    public function getCustomer(int $customerId): ?Customer;

    public function isPepAssociate(Customer $customer): bool;

    public function isHighRisk(Customer $customer): bool;

    public function findByIdNumber(string $idNumber): ?Customer;

    public function searchCustomers(string $query): array;

    public function decryptIdNumber(Customer $customer): ?string;

    public function decryptAddress(Customer $customer): string;
}
