<?php

namespace App\Repositories;

use App\Models\Customer;
use App\Services\Customer\CustomerService;
use App\Services\ThresholdService;
use Illuminate\Database\Eloquent\Collection;

class CustomerRepository
{
    public function __construct(
        protected ?ThresholdService $thresholdService = null,
    ) {
        $this->thresholdService ??= app(ThresholdService::class);
    }

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

    /**
     * Escape a user-supplied search term for use inside a LIKE pattern.
     *
     * Escapes the backslash first so it cannot alter the escape character,
     * then escapes the % and _ wildcards. Without this, a search for "\%" or
     * "100%" acts as a wildcard and matches records the user never searched for.
     */
    public static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    public function search(string $query): Collection
    {
        $pattern = '%'.self::escapeLike($query).'%';

        return Customer::whereRaw('full_name LIKE ? ESCAPE ?', [$pattern, '\\'])
            ->orWhereRaw('id_number_hash LIKE ? ESCAPE ?', [$pattern, '\\'])
            ->get();
    }

    public function searchActive(string $query, int $limit = 10, ?int $branchId = null): Collection
    {
        $escapedQuery = self::escapeLike($query);
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

        if ($branchId !== null) {
            // Branch scope: customers with at least one transaction at the
            // caller's branch (matches CustomerPolicy::view semantics).
            $q->whereHas('transactions', function ($q2) use ($branchId) {
                $q2->where('branch_id', $branchId);
            });
        }

        return $q->limit($limit)->get();
    }

    public function findActiveByIdNumberHash(string $idHash, ?int $branchId = null): ?Customer
    {
        // Identity (hash match + active) is mandatory; the optional branch scope
        // is grouped so its orWhere() alternative cannot escape the hash check
        // and return unrelated branch-local customers.
        $q = Customer::where('id_number_hash', $idHash)
            ->where('is_active', true);

        if ($branchId !== null) {
            $q->whereHas('transactions', function ($q2) use ($branchId) {
                $q2->where('branch_id', $branchId);
            });
        }

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
        // dedicated score threshold from config/thresholds.php.
        $highRiskScore = (int) config('thresholds.risk_scoring.score_high', 75);
        $rescreeningDays = config('thresholds.risk_scoring.rescreening_days', 30);

        return Customer::where('risk_score', '>=', $highRiskScore)
            ->orWhere('risk_assessed_at', '<', now()->subDays($rescreeningDays))
            ->get();
    }
}
