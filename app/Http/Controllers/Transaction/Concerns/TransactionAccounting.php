<?php

namespace App\Http\Controllers\Transaction\Concerns;

use App\Enums\AccountCode;
use App\Enums\TransactionType;
use App\Models\TillBalance;
use App\Models\Transaction;

trait TransactionAccounting
{
    /**
     * Update till balance after transaction
     */
    protected function updateTillBalance(TillBalance $tillBalance, string $type, string $amountLocal, string $amountForeign): void
    {
        $currentTotal = $tillBalance->transaction_total ?? '0';
        $foreignTotal = $tillBalance->foreign_total ?? '0';

        if ($type === TransactionType::Buy->value) {
            $tillBalance->update([
                'transaction_total' => $this->mathService->add($currentTotal, $amountLocal),
                'foreign_total' => $this->mathService->add($foreignTotal, $amountForeign),
            ]);
        } else {
            $tillBalance->update([
                'transaction_total' => $this->mathService->add($currentTotal, $amountLocal),
                'foreign_total' => $this->mathService->subtract($foreignTotal, $amountForeign),
            ]);
        }
    }

    /**
     * Create accounting journal entries for transaction
     */
    protected function createAccountingEntries(Transaction $transaction): void
    {
        $entries = [];

        if ($transaction->type->isBuy()) {
            $entries = [
                [
                    'account_code' => AccountCode::FOREIGN_CURRENCY_INVENTORY->value,
                    'debit' => $transaction->amount_local,
                    'credit' => '0',
                    'description' => "Buy {$transaction->amount_foreign} {$transaction->currency_code} @ {$transaction->rate}",
                ],
                [
                    'account_code' => AccountCode::CASH_MYR->value,
                    'debit' => '0',
                    'credit' => $transaction->amount_local,
                    'description' => "Payment for {$transaction->currency_code} purchase",
                ],
            ];
        } else {
            $position = $this->positionService->getPosition($transaction->currency_code);
            $avgCost = $position ? $position->avg_cost_rate : $transaction->rate;
            $costBasis = $this->mathService->multiply((string) $transaction->amount_foreign, $avgCost);
            $revenue = $this->mathService->subtract((string) $transaction->amount_local, $costBasis);
            $isGain = $this->mathService->compare($revenue, '0') >= 0;

            $entries = [
                [
                    'account_code' => AccountCode::CASH_MYR->value,
                    'debit' => $transaction->amount_local,
                    'credit' => '0',
                    'description' => "Sale of {$transaction->amount_foreign} {$transaction->currency_code}",
                ],
                [
                    'account_code' => AccountCode::FOREIGN_CURRENCY_INVENTORY->value,
                    'debit' => '0',
                    'credit' => $costBasis,
                    'description' => "Cost of {$transaction->currency_code} sold",
                ],
            ];

            if ($isGain) {
                $entries[] = [
                    'account_code' => AccountCode::FOREX_TRADING_REVENUE->value,
                    'debit' => '0',
                    'credit' => $revenue,
                    'description' => "Gain on {$transaction->currency_code} sale",
                ];
            } else {
                $entries[] = [
                    'account_code' => AccountCode::FOREX_LOSS->value,
                    'debit' => $this->mathService->multiply($revenue, '-1'),
                    'credit' => '0',
                    'description' => "Loss on {$transaction->currency_code} sale",
                ];
            }
        }

        $this->accountingService->createJournalEntry(
            $entries,
            'Transaction',
            $transaction->id,
            "Transaction #{$transaction->id} - {$transaction->type->value} {$transaction->currency_code}"
        );
    }
}
