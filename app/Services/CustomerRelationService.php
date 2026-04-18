<?php

namespace App\Services;

use App\Events\CustomerRelationAdded;
use App\Events\CustomerRelationRemoved;
use App\Models\Customer;
use App\Models\CustomerRelation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerRelationService
{
    public function __construct(
        protected AuditService $auditService,
    ) {}

    public function addRelation(int $customerId, array $data): CustomerRelation
    {
        return DB::transaction(function () use ($customerId, $data) {
            $relation = CustomerRelation::create([
                'customer_id' => $customerId,
                'related_customer_id' => $data['related_customer_id'] ?? null,
                'relation_type' => $data['relation_type'],
                'related_name' => $data['related_name'],
                'id_type' => $data['id_type'] ?? null,
                'id_number_encrypted' => $data['id_number_encrypted'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'nationality' => $data['nationality'] ?? null,
                'address' => $data['address'] ?? null,
                'is_pep' => $data['is_pep'] ?? false,
                'additional_info' => $data['additional_info'] ?? null,
            ]);

            $this->auditService->logCustomerRiskEvent('customer_relation_added', $customerId, [
                'relation_id' => $relation->id,
                'relation_type' => $relation->relation_type,
                'related_name' => $relation->related_name,
                'is_pep' => $relation->is_pep,
            ]);

            event(new CustomerRelationAdded($relation));

            return $relation;
        });
    }

    public function removeRelation(int $relationId): void
    {
        $relation = CustomerRelation::findOrFail($relationId);
        $customerId = $relation->customer_id;
        $isPep = $relation->is_pep;

        $relation->delete();

        $this->auditService->logCustomerRiskEvent('customer_relation_removed', $customerId, [
            'relation_id' => $relationId,
        ]);

        if ($isPep) {
            $customer = Customer::find($customerId);
            if ($customer) {
                $this->updateCustomerPepAssociateStatus($customer);
            }
        }

        event(new CustomerRelationRemoved($relation));
    }

    public function updateRelation(int $relationId, array $data): CustomerRelation
    {
        $relation = CustomerRelation::findOrFail($relationId);
        $relation->update($data);
        $relation->refresh();

        $this->auditService->logCustomerRiskEvent('customer_relation_updated', $relation->customer_id, [
            'relation_id' => $relationId,
            'changes' => $data,
        ]);

        return $relation;
    }

    public function getRelations(Customer $customer): Collection
    {
        return $customer->pepRelations()->with('relatedCustomer')->get();
    }

    public function getRelatedCustomers(Customer $customer): Collection
    {
        $relations = $customer->pepRelations;
        $relatedIds = $relations->pluck('related_customer_id')->filter();

        return Customer::whereIn('id', $relatedIds)->get();
    }

    public function isPepAssociate(Customer $customer): bool
    {
        return $customer->pepRelations()->where('is_pep', true)->exists();
    }

    public function isHighRiskRelation(Customer $customer): bool
    {
        return $customer->pepRelations()->where('is_pep', true)->exists();
    }

    public function calculateRelationRiskScore(Customer $customer): int
    {
        $score = 0;

        if ($customer->pep_status) {
            $score += 20;
        }

        $pepRelations = $customer->pepRelations()->where('is_pep', true)->count();
        $score += min($pepRelations * 10, 10);

        return min($score, 30);
    }

    public function getAllRelatedCustomers(Customer $customer): Collection
    {
        $directRelations = $customer->pepRelations;
        $reverseRelations = $customer->associateRelations;

        $allRelations = $directRelations->merge($reverseRelations);
        $relatedIds = $allRelations->pluck('related_customer_id')->filter()->unique();

        return Customer::whereIn('id', $relatedIds)->get();
    }

    public function updateCustomerPepAssociateStatus(Customer $customer): void
    {
        $hasPepAssociate = $this->isPepAssociate($customer);
        $customer->update(['is_pep_associate' => $hasPepAssociate]);
    }
}
