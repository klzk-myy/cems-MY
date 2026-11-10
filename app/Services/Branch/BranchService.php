<?php

namespace App\Services\Branch;

use App\Exceptions\Domain\BranchDeactivationException;
use App\Models\Branch;
use App\Models\Currency;
use App\Services\Accounting\CurrencyPositionLockService;
use App\Services\AuditService;
use App\Support\ActorContext;
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

        $branch = DB::transaction(function () use ($data): Branch {
            // Lock the parent before activating a child under it: keeps this
            // serialised against deactivateBranch() locking the same parent.
            if (($data['is_active'] ?? true) && ! empty($data['parent_id'])) {
                $parent = Branch::whereKey($data['parent_id'])->lockForUpdate()->firstOrFail();
                if (! $parent->is_active) {
                    throw new BranchDeactivationException('Cannot activate a branch under an inactive parent');
                }
            }

            if (! empty($data['is_main'])) {
                $this->ensureSingleMainBranch();
            }

            return Branch::create([
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
        });

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

        DB::transaction(function () use ($branch, $data): void {
            // Lock order: parent -> child. Activating under a parent takes
            // the parent lock first so it serialises against a concurrent
            // deactivateBranch() on that parent.
            if (($data['is_active'] ?? true) && ! empty($data['parent_id'])) {
                $parent = Branch::whereKey($data['parent_id'])->lockForUpdate()->firstOrFail();
                if (! $parent->is_active) {
                    throw new BranchDeactivationException('Cannot activate a branch under an inactive parent');
                }
            }

            $lockedBranch = Branch::whereKey($branch->id)->lockForUpdate()->firstOrFail();

            if (! empty($data['is_main']) && ! $lockedBranch->is_main) {
                $this->ensureSingleMainBranch();
            }

            $lockedBranch->update([
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

            $branch->refresh();
        });

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

        DB::transaction(function () use ($branch): void {
            // The branch row is the shared lock with createBranch/updateBranch
            // child-activation: a concurrent activation serialises here.
            $lockedBranch = Branch::whereKey($branch->id)->lockForUpdate()->firstOrFail();

            if ($lockedBranch->is_main) {
                throw new BranchDeactivationException('Cannot deactivate the main branch');
            }

            if ($lockedBranch->children()->where('is_active', true)->exists()) {
                throw new BranchDeactivationException('Cannot deactivate branch with active child branches');
            }

            $lockedBranch->update(['is_active' => false]);
        });

        $branch->refresh();

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

    protected function ensureSingleMainBranch(): void
    {
        Branch::where('is_main', true)->update(['is_main' => false]);
    }
}
