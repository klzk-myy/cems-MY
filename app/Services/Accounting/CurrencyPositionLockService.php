<?php

namespace App\Services\Accounting;

use App\Models\CurrencyPosition;
use App\Services\System\MathService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class CurrencyPositionLockService
{
    public function __construct(protected MathService $mathService) {}

    public function findForUpdate(string $branchId, string $currencyCode): ?CurrencyPosition
    {
        return CurrencyPosition::where('branch_id', $branchId)
            ->where('currency_code', $currencyCode)
            ->lockForUpdate()
            ->first();
    }

    /**
     * Lock an existing currency position or create a new zero-quantity one.
     *
     * This method uses {@see CurrencyPosition::lockForUpdate()} for pessimistic
     * locking. It runs within a database transaction to ensure the lock is held
     * for the duration of the caller's transaction.
     *
     * @param  string  $branchId  Branch identifier
     * @param  string  $currencyCode  Currency code (e.g., 'USD')
     * @return CurrencyPosition The locked or freshly created position
     */
    public function lock(string $branchId, string $currencyCode): CurrencyPosition
    {
        return DB::transaction(function () use ($branchId, $currencyCode) {
            $position = $this->findForUpdate($branchId, $currencyCode);

            if ($position) {
                return $position;
            }

            try {
                return CurrencyPosition::create([
                    'branch_id' => $branchId,
                    'currency_code' => $currencyCode,
                    'quantity' => '0',
                    'average_cost' => '0',
                    'total_cost' => '0',
                    'current_rate' => '0',
                    'current_value' => '0',
                    'unrealized_gain_loss' => '0',
                    'last_revalued_at' => null,
                ]);
            } catch (UniqueConstraintViolationException $e) {
                return CurrencyPosition::where('branch_id', $branchId)
                    ->where('currency_code', $currencyCode)
                    ->lockForUpdate()
                    ->firstOrFail();
            }
        });
    }

    public function adjust(CurrencyPosition $position, string $amount, string $operation): CurrencyPosition
    {
        $currentQuantity = (string) $position->quantity;
        $newQuantity = match ($operation) {
            'add' => $this->mathService->add($currentQuantity, $amount),
            'subtract' => $this->mathService->subtract($currentQuantity, $amount),
            default => throw new \InvalidArgumentException("Unknown position operation: {$operation}"),
        };

        $position->update(['quantity' => $newQuantity]);

        return $position->refresh();
    }
}
