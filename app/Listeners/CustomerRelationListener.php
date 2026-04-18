<?php

namespace App\Listeners;

use App\Events\CustomerRelationAdded;
use App\Events\CustomerRelationRemoved;
use App\Services\CustomerRelationService;

class CustomerRelationListener
{
    public function __construct(
        protected CustomerRelationService $relationService
    ) {}

    public function handleAdded(CustomerRelationAdded $event): void
    {
        $relation = $event->relation;

        if ($relation->is_pep) {
            $this->relationService->updateCustomerPepAssociateStatus($relation->customer);
        }
    }

    public function handleRemoved(CustomerRelationRemoved $event): void
    {
        $relation = $event->relation;

        if ($relation->is_pep) {
            $this->relationService->updateCustomerPepAssociateStatus($relation->customer);
        }
    }
}
