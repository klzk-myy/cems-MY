<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchPool;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\SystemLog;

class BranchPoolService
{
    protected MathService $mathService;

    public function __construct()
    {
        $this->mathService = new MathService;
    }

    public function getOrCreateForBranch(Branch $branch, string $currencyCode): BranchPool
    {
        return BranchPool::firstOrCreate(
            [
                'branch_id' => $branch->id,
                'currency_code' => $currencyCode,
            ],
            [
                'available_balance' => '0.0000',
                'allocated_balance' => '0.0000',
            ]
        );
    }

    public function getPoolBalance(Branch $branch, string $currencyCode): array
    {
        $pool = BranchPool::where('branch_id', $branch->id)
            ->where('currency_code', $currencyCode)
            ->first();

        if (! $pool) {
            return [
                'available' => '0.0000',
                'allocated' => '0.0000',
                'total' => '0.0000',
            ];
        }

        return [
            'available' => $pool->available_balance,
            'allocated' => $pool->allocated_balance,
            'total' => $this->mathService->add($pool->available_balance, $pool->allocated_balance),
        ];
    }

    public function allocateToTeller(Branch $branch, string $currencyCode, float|string $amount): bool
    {
        $amount = (string) $amount;

        $pool = BranchPool::where('branch_id', $branch->id)
            ->where('currency_code', $currencyCode)
            ->lockForUpdate()
            ->first();

        if (! $pool) {
            return false;
        }

        return $pool->allocate($amount);
    }

    public function deallocateFromTeller(Branch $branch, string $currencyCode, float|string $amount): bool
    {
        $amount = (string) $amount;

        $pool = BranchPool::where('branch_id', $branch->id)
            ->where('currency_code', $currencyCode)
            ->lockForUpdate()
            ->first();

        if (! $pool) {
            return false;
        }

        return $pool->deallocate($amount);
    }

    public function replenish(Branch $branch, string $currencyCode, float|string $amount, int $approvedBy): BranchPool
    {
        $amount = (string) $amount;

        return DB::transaction(function () use ($branch, $currencyCode, $amount) {
            $pool = BranchPool::where('branch_id', $branch->id)
                ->where('currency_code', $currencyCode)
                ->lockForUpdate()
                ->first();

            if (! $pool) {
                $pool = BranchPool::create([
                    'branch_id' => $branch->id,
                    'currency_code' => $currencyCode,
                    'available_balance' => '0.0000',
                    'allocated_balance' => '0.0000',
                ]);
                $pool = BranchPool::where('branch_id', $branch->id)
                    ->where('currency_code', $currencyCode)
                    ->lockForUpdate()
                    ->first();
            }

            $pool->available_balance = $this->mathService->add($pool->available_balance, $amount);
            $pool->save();

            Log::info('Branch pool replenished', [
                'branch_id' => $branch->id,
                'currency_code' => $currencyCode,
                'amount' => $amount,
                'approved_by' => $approvedBy,
                'pool_id' => $pool->id,
            ]);

            return $pool;
        });
    }

    public function getAllPoolsForBranch(Branch $branch): Collection
    {
        return BranchPool::where('branch_id', $branch->id)->get();
    }

    public function getAvailablePoolsForBranch(Branch $branch): Collection
    {
        return BranchPool::where('branch_id', $branch->id)
            ->where('available_balance', '>', 0)
            ->get();
    }
}
