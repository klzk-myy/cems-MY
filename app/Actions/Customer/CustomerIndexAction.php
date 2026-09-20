<?php

namespace App\Actions\Customer;

use App\Models\Customer;
use App\Models\User;
use App\Support\LikeEscaper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Build the customer index query with search, filters, branch scoping, and pagination.
 *
 * Extracted from the duplicated query construction in the web and API
 * CustomerController hierarchies.
 */
final class CustomerIndexAction
{
    /**
     * @param  array  $filters  {search, risk_rating, is_active, pep_status, sanction_hit, is_frozen, nationality, id_type, cdd_level, customer_type, sort_by, sort_dir, per_page, branch_scope}
     * @param  User|null  $user  Authenticated user (admin sees everything)
     */
    public function execute(array $filters, ?User $user): LengthAwarePaginator
    {
        $isAdmin = $user?->isAdmin() ?? false;

        /** @var Builder $query */
        $query = Customer::query();

        if (! $isAdmin) {
            // Customers are company-wide, but the caller must still belong to
            // a branch (matches CustomerPolicy::viewAny). Users without one
            // get an empty result.
            if (! $user?->branch_id) {
                $query->whereRaw('1 = 0');
            }
        } elseif ($branchScope = $filters['branch_scope'] ?? null) {
            $query->whereHas('transactions', fn ($t) => $t->where('branch_id', $branchScope));
        }

        if (! empty($filters['search'] ?? null)) {
            $search = '%'.LikeEscaper::escape($filters['search']).'%';
            $query->whereRaw('full_name LIKE ? ESCAPE ?', [$search, '\\']);
        }

        if (! empty($filters['risk_rating'] ?? null)) {
            $query->where('risk_rating', $filters['risk_rating']);
        }

        if (array_key_exists('is_active', $filters)) {
            $query->where('is_active', $filters['is_active'] === '1');
        }

        if (array_key_exists('pep_status', $filters)) {
            $query->where('pep_status', $filters['pep_status'] === '1');
        }

        if (array_key_exists('sanction_hit', $filters)) {
            $query->where('sanction_hit', $filters['sanction_hit'] === '1');
        }

        if (array_key_exists('is_frozen', $filters)) {
            $query->where('is_frozen', $filters['is_frozen'] === '1');
        }

        if (! empty($filters['nationality'] ?? null)) {
            $query->where('nationality', $filters['nationality']);
        }

        if (! empty($filters['id_type'] ?? null)) {
            $query->where('id_type', $filters['id_type']);
        }

        if (! empty($filters['cdd_level'] ?? null)) {
            $query->where('cdd_level', $filters['cdd_level']);
        }

        if (! empty($filters['customer_type'] ?? null)) {
            $query->where('customer_type', $filters['customer_type']);
        }

        $allowedSortColumns = ['created_at', 'updated_at', 'full_name', 'risk_rating', 'is_active', 'pep_status', 'nationality'];
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSortColumns, true)
            ? $filters['sort_by']
            : 'created_at';
        $sortDir = strtolower($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortBy, $sortDir);

        return $query->paginate(min(100, max(1, (int) ($filters['per_page'] ?? 20))));
    }
}
