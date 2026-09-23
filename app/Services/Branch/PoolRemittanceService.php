<?php

namespace App\Services\Branch;

use App\Enums\AccountMappingKey;
use App\Enums\PoolRemittanceStatus;
use App\Enums\RemittanceGlLeg;
use App\Exceptions\Domain\TransactionCreationException;
use App\Exceptions\Domain\TransactionValidationException;
use App\Models\Branch;
use App\Models\BranchPool;
use App\Models\Currency;
use App\Models\JournalEntry;
use App\Models\PoolRemittance;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\AccountMappingService;
use App\Services\AuditService;
use App\Services\System\MathService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Two-step cash remittance between a trading branch and head office.
 *
 * Initiation debits the sender's pool and posts Dr 2300 clearing /
 * Cr cash-or-inventory on the sender's ledger chain; acknowledgement
 * posts Dr cash-or-inventory / Cr 2300 on the receiver's chain and
 * credits the receiver's pool. The clearing account therefore holds
 * exactly the value of cash in transit and nets to zero once every
 * dispatched amount is acknowledged. Exactly one side of every
 * remittance is the head-office branch — branches move stock between
 * each other via StockTransfer, cash only via HQ.
 */
class PoolRemittanceService
{
    public function __construct(
        protected AuditService $auditService,
        protected MathService $mathService,
        protected AccountMappingService $accountMappingService,
        protected AccountingService $accountingService,
        protected BranchPoolService $poolService,
    ) {}

    public function initiate(Branch $from, Branch $to, string $currencyCode, float|string $amountMyr, int $initiatedBy, ?string $notes = null): PoolRemittance
    {
        $amountMyr = (string) $amountMyr;

        if ($from->id === $to->id) {
            throw new TransactionValidationException(message: 'A branch cannot remit to itself');
        }

        if ($from->isHeadOffice() === $to->isHeadOffice()) {
            throw new TransactionValidationException(message: 'Remittances move cash between a trading branch and head office only');
        }

        if ($currencyCode !== Currency::baseCurrency()) {
            throw new TransactionValidationException(message: 'Remittances move MYR capital only — head office holds no foreign stock; move foreign currency via stock transfer');
        }

        if ($this->mathService->compare($amountMyr, '0') <= 0) {
            throw new TransactionValidationException(message: 'Remittance amount must be a positive number');
        }

        // A concurrent initiation from another branch can generate the same
        // first-of-day sequence number before either commits; the unique
        // index on remittance_number rejects the loser, so retry the whole
        // transaction (number regenerated) rather than surfacing a 500.
        for ($attempt = 0; ; $attempt++) {
            try {
                return DB::transaction(function () use ($from, $to, $currencyCode, $amountMyr, $initiatedBy, $notes) {
                    $pool = BranchPool::where('branch_id', $from->id)
                        ->where('currency_code', $currencyCode)
                        ->lockForUpdate()
                        ->first();

                    if (! $pool || $this->mathService->compare($pool->available_balance, $amountMyr) < 0) {
                        throw new TransactionValidationException(message: "Insufficient available balance in the {$currencyCode} pool");
                    }

                    $pool->available_balance = $this->mathService->subtract($pool->available_balance, $amountMyr);
                    $pool->save();

                    $remittance = PoolRemittance::create([
                        'remittance_number' => PoolRemittance::generateRemittanceNumber(),
                        'from_branch_id' => $from->id,
                        'to_branch_id' => $to->id,
                        'currency_code' => $currencyCode,
                        'amount_myr' => $amountMyr,
                        'status' => PoolRemittanceStatus::Pending,
                        'initiated_by' => $initiatedBy,
                        'initiated_at' => now(),
                        'notes' => $notes,
                    ]);

                    $remittance->out_journal_entry_id = $this->postRemittanceGl($remittance, $from, RemittanceGlLeg::Dispatch, $initiatedBy)->id;
                    $remittance->save();

                    Log::info('Pool remittance initiated', [
                        'remittance_number' => $remittance->remittance_number,
                        'from_branch_id' => $from->id,
                        'to_branch_id' => $to->id,
                        'currency_code' => $currencyCode,
                        'amount_myr' => $amountMyr,
                        'initiated_by' => $initiatedBy,
                    ]);

                    $this->auditService->logBranchEvent(
                        'pool_remittance_initiated',
                        $from->id,
                        [
                            'remittance_id' => $remittance->id,
                            'remittance_number' => $remittance->remittance_number,
                            'to_branch_id' => $to->id,
                            'currency_code' => $currencyCode,
                            'amount_myr' => $amountMyr,
                            'initiated_by' => $initiatedBy,
                        ]
                    );

                    return $remittance;
                });
            } catch (UniqueConstraintViolationException $e) {
                // Only remittance_number collisions are retryable — any other
                // unique violation is a data/programming error and must surface
                // unchanged rather than being masked as a retry.
                if (! str_contains($e->getMessage(), 'remittance_number')) {
                    throw $e;
                }

                if ($attempt >= 2) {
                    throw new TransactionCreationException(
                        'Unable to allocate a unique remittance number after 3 attempts. Please retry.'
                    );
                }
            }
        }
    }

    public function acknowledge(PoolRemittance $remittance, int $acknowledgedBy): PoolRemittance
    {
        return DB::transaction(function () use ($remittance, $acknowledgedBy) {
            $remittance = PoolRemittance::whereKey($remittance->id)->lockForUpdate()->firstOrFail();

            if (! $remittance->isPending()) {
                throw new TransactionValidationException(message: "Remittance {$remittance->remittance_number} is {$remittance->status->value} and cannot be acknowledged");
            }

            if ($remittance->initiated_by === $acknowledgedBy) {
                throw new TransactionValidationException(message: "Remittance {$remittance->remittance_number} cannot be acknowledged by the user who initiated it");
            }

            $to = $remittance->toBranch;
            $pool = $this->poolService->getOrCreateForBranch($to, $remittance->currency_code);
            $pool = BranchPool::whereKey($pool->id)->lockForUpdate()->firstOrFail();

            $pool->available_balance = $this->mathService->add($pool->available_balance, (string) $remittance->amount_myr);
            $pool->save();

            $remittance->ack_journal_entry_id = $this->postRemittanceGl($remittance, $to, RemittanceGlLeg::Receipt, $acknowledgedBy)->id;
            $remittance->status = PoolRemittanceStatus::Acknowledged;
            $remittance->acknowledged_by = $acknowledgedBy;
            $remittance->acknowledged_at = now();
            $remittance->save();

            Log::info('Pool remittance acknowledged', [
                'remittance_number' => $remittance->remittance_number,
                'to_branch_id' => $to->id,
                'acknowledged_by' => $acknowledgedBy,
            ]);

            $this->auditService->logBranchEvent(
                'pool_remittance_acknowledged',
                $to->id,
                [
                    'remittance_id' => $remittance->id,
                    'remittance_number' => $remittance->remittance_number,
                    'from_branch_id' => $remittance->from_branch_id,
                    'currency_code' => $remittance->currency_code,
                    'amount_myr' => (string) $remittance->amount_myr,
                    'acknowledged_by' => $acknowledgedBy,
                ]
            );

            return $remittance;
        });
    }

    public function cancel(PoolRemittance $remittance, int $cancelledBy, ?string $reason = null): PoolRemittance
    {
        return DB::transaction(function () use ($remittance, $cancelledBy, $reason) {
            $remittance = PoolRemittance::whereKey($remittance->id)->lockForUpdate()->firstOrFail();

            if (! $remittance->isPending()) {
                throw new TransactionValidationException(message: "Remittance {$remittance->remittance_number} is {$remittance->status->value} and cannot be cancelled");
            }

            $from = $remittance->fromBranch;
            $pool = BranchPool::where('branch_id', $from->id)
                ->where('currency_code', $remittance->currency_code)
                ->lockForUpdate()
                ->firstOrFail();

            $pool->available_balance = $this->mathService->add($pool->available_balance, (string) $remittance->amount_myr);
            $pool->save();

            $remittance->cancel_journal_entry_id = $this->postRemittanceGl($remittance, $from, RemittanceGlLeg::Reversal, $cancelledBy)->id;
            $remittance->status = PoolRemittanceStatus::Cancelled;
            $remittance->cancelled_by = $cancelledBy;
            $remittance->cancelled_at = now();
            if ($reason !== null && $reason !== '') {
                $remittance->notes = trim(($remittance->notes ?? '')."\nCancelled: {$reason}");
            }
            $remittance->save();

            Log::info('Pool remittance cancelled', [
                'remittance_number' => $remittance->remittance_number,
                'from_branch_id' => $from->id,
                'cancelled_by' => $cancelledBy,
            ]);

            $this->auditService->logBranchEvent(
                'pool_remittance_cancelled',
                $from->id,
                [
                    'remittance_id' => $remittance->id,
                    'remittance_number' => $remittance->remittance_number,
                    'to_branch_id' => $remittance->to_branch_id,
                    'currency_code' => $remittance->currency_code,
                    'amount_myr' => (string) $remittance->amount_myr,
                    'cancelled_by' => $cancelledBy,
                    'reason' => $reason,
                ]
            );

            return $remittance;
        });
    }

    /**
     * Post the GL leg of a remittance through the inter-branch clearing
     * account (suspense.hq / 2300). Initiation credits the currency's
     * cash/inventory account on the SENDER branch's ledger chain and
     * debits clearing; acknowledgement (and cancellation's reversal)
     * debit cash/inventory on the branch where the cash landed and
     * credit clearing. Same convention as StockTransferService::postTransferGl.
     * The entry is authored by the user performing THIS leg — the
     * initiator on dispatch, the acknowledger/canceller on the inbound legs.
     */
    private function postRemittanceGl(PoolRemittance $remittance, Branch $branch, RemittanceGlLeg $leg, int $actorId): JournalEntry
    {
        $clearingAccount = $this->accountMappingService->code(AccountMappingKey::SuspenseHq);
        $amountMyr = (string) $remittance->amount_myr;

        $accountCode = $remittance->currency_code === Currency::baseCurrency()
            ? $this->accountMappingService->code(AccountMappingKey::CashMyr)
            : $this->accountMappingService->forCurrency('inventory', $remittance->currency_code);

        $lines = [
            $leg->isInbound()
                ? ['account_code' => $accountCode, 'debit' => $amountMyr, 'description' => "Remittance {$remittance->remittance_number} — {$remittance->currency_code} {$leg->verb()}"]
                : ['account_code' => $accountCode, 'credit' => $amountMyr, 'description' => "Remittance {$remittance->remittance_number} — {$remittance->currency_code} {$leg->verb()}"],
            $leg->isInbound()
                ? ['account_code' => $clearingAccount, 'credit' => $amountMyr, 'description' => "Remittance {$remittance->remittance_number} — in transit"]
                : ['account_code' => $clearingAccount, 'debit' => $amountMyr, 'description' => "Remittance {$remittance->remittance_number} — in transit"],
        ];

        return $this->accountingService->createJournalEntry(
            $lines,
            'PoolRemittance',
            (int) $remittance->id,
            "Pool remittance {$remittance->remittance_number} ({$leg->name})",
            null,
            $actorId,
            $branch->id
        );
    }
}
