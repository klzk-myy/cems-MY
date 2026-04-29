<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\SystemLog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class BranchService
{
    public function __construct(
        protected AuditService $auditService,
    ) {}

    public function getBranchTypes(): array
    {
        return [
            'head_office' => 'Head Office',
            'branch' => 'Branch',
            'sub_branch' => 'Sub-Branch',
        ];
    }

    public function getParentBranches(?int $excludeId = null): Collection
    {
        $query = Branch::where('is_active', true)->orderBy('code');

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->get();
    }

    public function createBranch(array $data, ?int $userId = null, string $ip = ''): Branch
    {
        $userId = $userId ?? Auth::id();

        if (! empty($data['is_main'])) {
            $this->ensureSingleMainBranch();
        }

        $branch = Branch::create([
            'code' => $data['code'],
            'name' => $data['name'],
            'type' => $data['type'],
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'country' => $data['country'] ?? 'Malaysia',
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'is_main' => $data['is_main'] ?? false,
            'parent_id' => $data['parent_id'] ?? null,
        ]);

        SystemLog::create([
            'user_id' => $userId,
            'action' => 'branch_created',
            'entity_type' => 'Branch',
            'entity_id' => $branch->id,
            'new_values' => [
                'code' => $branch->code,
                'name' => $branch->name,
                'type' => $branch->type,
            ],
            'ip_address' => $ip,
        ]);

        return $branch;
    }

    public function updateBranch(Branch $branch, array $data, ?int $userId = null, string $ip = ''): Branch
    {
        $userId = $userId ?? Auth::id();

        $oldValues = [
            'code' => $branch->code,
            'name' => $branch->name,
            'type' => $branch->type,
            'is_active' => $branch->is_active,
            'is_main' => $branch->is_main,
        ];

        if (! empty($data['is_main']) && ! $branch->is_main) {
            $this->ensureSingleMainBranch();
        }

        $branch->update([
            'code' => $data['code'],
            'name' => $data['name'],
            'type' => $data['type'],
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'country' => $data['country'] ?? 'Malaysia',
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'is_main' => $data['is_main'] ?? false,
            'parent_id' => $data['parent_id'] ?? null,
        ]);

        SystemLog::create([
            'user_id' => $userId,
            'action' => 'branch_updated',
            'entity_type' => 'Branch',
            'entity_id' => $branch->id,
            'old_values' => $oldValues,
            'new_values' => [
                'code' => $branch->code,
                'name' => $branch->name,
                'type' => $branch->type,
                'is_active' => $branch->is_active,
                'is_main' => $branch->is_main,
            ],
            'ip_address' => $ip,
        ]);

        return $branch;
    }

    public function deactivateBranch(Branch $branch, ?int $userId = null, string $ip = ''): void
    {
        $userId = $userId ?? Auth::id();

        if ($branch->is_main) {
            throw new \RuntimeException('Cannot deactivate the main branch');
        }

        if ($branch->children()->where('is_active', true)->exists()) {
            throw new \RuntimeException('Cannot deactivate branch with active child branches');
        }

        $branch->update(['is_active' => false]);

        SystemLog::create([
            'user_id' => $userId,
            'action' => 'branch_deactivated',
            'entity_type' => 'Branch',
            'entity_id' => $branch->id,
            'old_values' => [
                'code' => $branch->code,
                'name' => $branch->name,
                'is_active' => true,
            ],
            'new_values' => [
                'code' => $branch->code,
                'name' => $branch->name,
                'is_active' => false,
            ],
            'ip_address' => $ip,
        ]);
    }

    public function getBranchStats(Branch $branch): array
    {
        return [
            'user_count' => $branch->users()->count(),
            'counter_count' => $branch->counters()->count(),
            'transaction_today' => $branch->transactions()
                ->whereDate('created_at', now()->toDateString())
                ->count(),
            'transaction_month' => $branch->transactions()
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
        ];
    }

    protected function ensureSingleMainBranch(): void
    {
        Branch::where('is_main', true)->update(['is_main' => false]);
    }
}
