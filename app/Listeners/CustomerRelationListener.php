<?php

namespace App\Listeners;

use App\Events\CustomerRelationAdded;
use App\Events\CustomerRelationRemoved;
use App\Models\Customer;
use App\Services\Customer\CustomerRelationService;

class CustomerRelationListener
{
    public function __construct(
        protected CustomerRelationService $relationService
    ) {}

    public function handleAdded(CustomerRelationAdded $event): void
    {
        $relation = $event->relation;

        if (! $relation->is_pep) {
            return;
        }

        $customer = $relation->customer;

        if ($customer instanceof Customer) {
            $this->relationService->updateCustomerPepAssociateStatus($customer);
        }
    }

    public function handleRemoved(CustomerRelationRemoved $event): void
    {
        $relation = $event->relation;

        if (! $relation->is_pep) {
            return;
        }

        $customer = $relation->customer;

        if ($customer instanceof Customer) {
            $this->relationService->updateCustomerPepAssociateStatus($customer);
        }
    }
}
