<?php

namespace App\Services\Branch;

use App\Exceptions\Domain\BranchDeactivationException;
use App\Models\Branch;
use App\Models\Currency;
use App\Services\Accounting\CurrencyPositionLockService;
use App\Services\AuditService;
use App\Support\ActorContext;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class BranchService
{
    public function __construct(
        protected AuditService $auditService,
        protected BranchPoolService $branchPoolService,
        protected CurrencyPositionLockService $positionLockService,
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
        $userId = $userId ?? ActorContext::capture()->userId;

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

        // Trading branches get a zero pool and zero position per active
        // currency so the branch can hold stock immediately — mirrors the
        // per-branch provisioning done when a new currency is created.
        if ($branch->canTrade()) {
            DB::transaction(function () use ($branch): void {
                foreach (Currency::where('is_active', true)->get() as $currency) {
                    $this->branchPoolService->getOrCreateForBranch($branch, $currency->code);
                    $this->positionLockService->lock((string) $branch->id, $currency->code);
                }
            });
        }

        $this->auditService->log(
            'branch_created',
            $userId,
            'Branch',
            $branch->id,
            [],
            [
                'code' => $branch->code,
                'name' => $branch->name,
                'type' => $branch->type,
            ]
        );

        return $branch;
    }

    public function updateBranch(Branch $branch, array $data, ?int $userId = null, string $ip = ''): Branch
    {
        $userId = $userId ?? ActorContext::capture()->userId;

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

        $this->auditService->log(
            'branch_updated',
            $userId,
            'Branch',
            $branch->id,
            $oldValues,
            [
                'code' => $branch->code,
                'name' => $branch->name,
                'type' => $branch->type,
                'is_active' => $branch->is_active,
                'is_main' => $branch->is_main,
            ]
        );

        return $branch;
    }

    public function deactivateBranch(Branch $branch, ?int $userId = null, string $ip = ''): void
    {
        $userId = $userId ?? ActorContext::capture()->userId;

        if ($branch->is_main) {
            throw new BranchDeactivationException('Cannot deactivate the main branch');
        }

        if ($branch->children()->where('is_active', true)->exists()) {
            throw new BranchDeactivationException('Cannot deactivate branch with active child branches');
        }

        $branch->update(['is_active' => false]);

        $this->auditService->log(
            'branch_deactivated',
            $userId,
            'Branch',
            $branch->id,
            [
                'code' => $branch->code,
                'name' => $branch->name,
                'is_active' => true,
            ],
            [
                'code' => $branch->code,
                'name' => $branch->name,
                'is_active' => false,
            ]
        );
    }

    public function getBranchStats(Branch $branch): array
    {
        return [
            'user_count' => $branch->users()->count(),
            'counter_count' => $branch->counters()->count(),
            'transaction_today' => $branch->transactions()
                ->whereBetween('created_at', [Carbon::parse(now()->toDateString())->startOfDay(), Carbon::parse(now()->toDateString())->endOfDay()])
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
