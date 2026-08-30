<?php

namespace App\Services\Contracts;

use App\Models\Customer;

interface CustomerServiceInterface
{
    // Note: Several controller-used methods are missing from this interface:
    // createCustomerAction, updateCustomerAction, closeCustomer,
    // getCustomerShowData, getTransactionStats, uploadDocument.
    // searchCustomers is also called with an optional $branchId parameter
    // that is not declared here.
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
