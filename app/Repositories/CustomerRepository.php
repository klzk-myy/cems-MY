<?php

namespace App\Repositories;

use App\Models\Customer;
use App\Services\Customer\CustomerService;
use App\Services\ThresholdService;
use App\Support\LikeEscaper;
use Illuminate\Database\Eloquent\Collection;

class CustomerRepository
{
    public function __construct(
        protected ThresholdService $thresholdService,
    ) {}

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
        $pattern = '%'.LikeEscaper::escape($query).'%';

        return Customer::whereRaw('full_name LIKE ? ESCAPE ?', [$pattern, '\\'])
            ->orWhereRaw('id_number_hash LIKE ? ESCAPE ?', [$pattern, '\\'])
            ->limit(50)
            ->get();
    }

    public function searchActive(string $query, int $limit = 10, ?int $branchId = null): Collection
    {
        $escapedQuery = LikeEscaper::escape($query);
        $pattern = '%'.$escapedQuery.'%';

        // Explicit ESCAPE clause: SQLite has no default escape character, MySQL uses
        // backslash by default, so without this the escaped wildcards are meaningless
        // on some drivers.
        //
        // The identity predicates stay inside a grouped closure so their OR
        // branches cannot escape the mandatory is_active filter, and any branch
        // scoping is its own grouped AND clause rather than a trailing orWhere
        // that would return customers matching neither name nor ID hash.
        $q = Customer::where(function ($query) use ($pattern) {
            $query->whereRaw('full_name LIKE ? ESCAPE ?', [$pattern, '\\'])
                ->orWhereRaw('id_number_hash LIKE ? ESCAPE ?', [$pattern, '\\']);
        })
            ->where('is_active', true);

        // Customers are company-wide — $branchId is accepted for signature
        // compatibility but no longer narrows the result set.

        return $q->limit($limit)->get();
    }

    public function findActiveByIdNumberHash(string $idHash, ?int $branchId = null): ?Customer
    {
        // Identity (hash match + active) is mandatory. Customers are
        // company-wide — $branchId no longer narrows the lookup.
        $q = Customer::where('id_number_hash', $idHash)
            ->where('is_active', true);

        return $q->first();
    }

    public function getByIds(array $customerIds): Collection
    {
        return Customer::whereIn('id', $customerIds)->get();
    }

    public function getCustomersNeedingRescreening(): Collection
    {
        // risk_score is on a 0-100 scale; the amount-based getRiskHighThreshold()
        // (MYR cash) is NOT comparable to a score, so this query uses the
        // dedicated score threshold.
        $highRiskScore = (int) $this->thresholdService->get('risk_scoring', 'score_high', 75);
        $rescreeningDays = (int) $this->thresholdService->get('risk_scoring', 'rescreening_days', 30);

        return Customer::where('risk_score', '>=', $highRiskScore)
            ->orWhere('risk_assessed_at', '<', now()->subDays($rescreeningDays))
            ->get();
    }
}
