<?php

namespace App\Services\Branch;

use App\Exceptions\Domain\TransactionValidationException;
use App\Models\Branch;
use App\Models\BranchPool;
use App\Services\AuditService;
use App\Services\System\MathService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BranchPoolService
{
    public function __construct(
        protected AuditService $auditService,
        protected MathService $mathService,
    ) {}

    public function getOrCreateForBranch(Branch $branch, string $currencyCode): BranchPool
    {
        return DB::transaction(function () use ($branch, $currencyCode) {
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
        });
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

        return DB::transaction(function () use ($branch, $currencyCode, $amount) {
            $pool = BranchPool::where('branch_id', $branch->id)
                ->where('currency_code', $currencyCode)
                ->lockForUpdate()
                ->first();

            if (! $pool) {
                return false;
            }

            return $pool->allocate($amount);
        });
    }

    public function deallocateFromTeller(Branch $branch, string $currencyCode, float|string $amount): bool
    {
        $amount = (string) $amount;

        return DB::transaction(function () use ($branch, $currencyCode, $amount) {
            $pool = BranchPool::where('branch_id', $branch->id)
                ->where('currency_code', $currencyCode)
                ->lockForUpdate()
                ->first();

            if (! $pool) {
                return false;
            }

            return $pool->deallocate($amount);
        });
    }

    /**
     * Consume part of the teller earmark for stock that left the branch —
     * a sell hands the foreign currency to the customer, so the amount is
     * removed from allocated without touching available. Shortfalls
     * (historical drift) clamp at zero and log rather than blocking the
     * transaction — currency_positions remains the authoritative gate.
     */
    public function consumeTellerEarmark(Branch $branch, string $currencyCode, float|string $amount): void
    {
        $amount = (string) $amount;

        DB::transaction(function () use ($branch, $currencyCode, $amount) {
            $pool = BranchPool::where('branch_id', $branch->id)
                ->where('currency_code', $currencyCode)
                ->lockForUpdate()
                ->first();

            if (! $pool) {
                Log::warning('Branch pool earmark consume skipped — no pool row', [
                    'branch_id' => $branch->id,
                    'currency_code' => $currencyCode,
                    'amount' => $amount,
                ]);

                return;
            }

            $consumed = $pool->consumeAllocated($amount);

            if ($this->mathService->compare($consumed, $amount) < 0) {
                Log::warning('Branch pool earmark consume partially uncovered', [
                    'branch_id' => $branch->id,
                    'currency_code' => $currencyCode,
                    'amount' => $amount,
                    'consumed' => $consumed,
                    'pool_id' => $pool->id,
                ]);
            }
        });
    }

    /**
     * Earmark stock that entered teller custody from outside the pool —
     * a buy brings in foreign currency the customer sold to the teller.
     */
    public function growTellerEarmark(Branch $branch, string $currencyCode, float|string $amount): void
    {
        $amount = (string) $amount;

        DB::transaction(function () use ($branch, $currencyCode, $amount) {
            $pool = BranchPool::where('branch_id', $branch->id)
                ->where('currency_code', $currencyCode)
                ->lockForUpdate()
                ->first();

            if (! $pool) {
                Log::warning('Branch pool earmark grow skipped — no pool row', [
                    'branch_id' => $branch->id,
                    'currency_code' => $currencyCode,
                    'amount' => $amount,
                ]);

                return;
            }

            $pool->growAllocated($amount);
        });
    }

    /**
     * Debit the branch's available pool balance (e.g. stock dispatched to
     * another branch). Pools predate transfer-driven tracking and may not
     * cover the dispatched amount, so the debit is clamped at zero and any
     * uncovered shortfall is logged for reconciliation rather than blocking
     * the transfer — currency_positions remains the authoritative stock gate.
     *
     * @return string The amount actually debited (clamped at the pool's
     *                available balance), as a numeric string.
     */
    public function debit(Branch $branch, string $currencyCode, float|string $amount, ?int $userId = null): string
    {
        $amount = (string) $amount;

        return DB::transaction(function () use ($branch, $currencyCode, $amount, $userId) {
            $pool = BranchPool::where('branch_id', $branch->id)
                ->where('currency_code', $currencyCode)
                ->lockForUpdate()
                ->first();

            if (! $pool) {
                Log::warning('Branch pool debit skipped — no pool row', [
                    'branch_id' => $branch->id,
                    'currency_code' => $currencyCode,
                    'amount' => $amount,
                ]);

                return '0';
            }

            $covered = $this->mathService->compare($pool->available_balance, $amount) >= 0
                ? $amount
                : $pool->available_balance;

            if ($this->mathService->compare($covered, '0') <= 0) {
                Log::warning('Branch pool debit uncovered — pool is empty', [
                    'branch_id' => $branch->id,
                    'currency_code' => $currencyCode,
                    'amount' => $amount,
                    'pool_id' => $pool->id,
                ]);

                return '0';
            }

            $pool->available_balance = $this->mathService->subtract($pool->available_balance, $covered);
            $pool->save();

            $shortfall = $this->mathService->subtract($amount, $covered);
            if ($this->mathService->compare($shortfall, '0') > 0) {
                Log::warning('Branch pool debit partially uncovered', [
                    'branch_id' => $branch->id,
                    'currency_code' => $currencyCode,
                    'amount' => $amount,
                    'shortfall' => $shortfall,
                    'pool_id' => $pool->id,
                ]);
            }

            $this->auditService->logBranchEvent(
                'branch_pool_debited',
                $branch->id,
                [
                    'currency_code' => $currencyCode,
                    'amount' => $amount,
                    'debited' => $covered,
                    'pool_id' => $pool->id,
                    'user_id' => $userId,
                ]
            );

            return $covered;
        });
    }

    /**
     * Debit the full requested amount or fail — unlike debit(), which
     * clamps at the pool balance for transfer dispatch, a manual pool
     * adjustment must either move the requested amount in full or error.
     * Runs the balance check under the row lock inside a transaction and
     * writes a branch_pool_debited audit event.
     */
    public function debitOrFail(Branch $branch, string $currencyCode, float|string $amount, ?int $userId = null): void
    {
        $amount = (string) $amount;

        DB::transaction(function () use ($branch, $currencyCode, $amount, $userId) {
            $pool = BranchPool::where('branch_id', $branch->id)
                ->where('currency_code', $currencyCode)
                ->lockForUpdate()
                ->first();

            if (! $pool || $this->mathService->compare($pool->available_balance, $amount) < 0) {
                throw new TransactionValidationException(
                    message: "Insufficient available balance in the {$currencyCode} pool"
                );
            }

            $pool->available_balance = $this->mathService->subtract($pool->available_balance, $amount);
            $pool->save();

            $this->auditService->logBranchEvent(
                'branch_pool_debited',
                $branch->id,
                [
                    'currency_code' => $currencyCode,
                    'amount' => $amount,
                    'debited' => $amount,
                    'pool_id' => $pool->id,
                    'user_id' => $userId,
                ]
            );
        });
    }

    public function replenish(Branch $branch, string $currencyCode, float|string $amount, int $approvedBy): BranchPool
    {
        $amount = (string) $amount;

        return DB::transaction(function () use ($branch, $currencyCode, $amount, $approvedBy) {
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

            $this->auditService->logBranchEvent(
                'branch_pool_replenished',
                $branch->id,
                [
                    'currency_code' => $currencyCode,
                    'amount' => $amount,
                    'pool_id' => $pool->id,
                    'approved_by' => $approvedBy,
                ]
            );

            return $pool;
        });
    }

    public function getAllPoolsForBranch(Branch $branch): Collection
    {
        return BranchPool::where('branch_id', $branch->id)->with('branch')->get();
    }

    public function getAvailablePoolsForBranch(Branch $branch): Collection
    {
        return BranchPool::where('branch_id', $branch->id)
            ->where('available_balance', '>', 0)
            ->get();
    }
}
