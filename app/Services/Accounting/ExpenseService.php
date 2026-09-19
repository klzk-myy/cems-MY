<?php

namespace App\Services\Accounting;

use App\Enums\AccountMappingKey;
use App\Exceptions\Domain\AccountingPeriodException;
use App\Exceptions\Domain\InsufficientPettyCashException;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\AuditService;
use App\Services\System\MathService;
use Illuminate\Support\Facades\DB;

/**
 * ExpenseService
 *
 * Branch petty-cash expense posting. Every expense debits the chosen
 * expense account and credits the petty cash account (1050), stamped with
 * the branch so branch P&L stays correct. Posting is direct — no approval
 * step — per the org model: holders of post_expenses post against their
 * own branch; cross-branch roles (accountant, admin) may post for any
 * branch. Funding floats is admin-only.
 */
class ExpenseService
{
    public function __construct(
        protected AccountingService $accountingService,
        protected MathService $mathService,
        protected AuditService $auditService,
        protected AccountMappingService $accountMappingService,
    ) {}

    /**
     * Post a petty-cash expense for a branch.
     *
     * @throws InsufficientPettyCashException When the branch float cannot cover the amount
     * @throws AccountingPeriodException When the entry is unbalanced or the period is closed
     */
    public function postExpense(
        Branch $branch,
        User $poster,
        string $accountCode,
        string $category,
        string $description,
        string $amountMyr,
        ?string $expenseDate = null
    ): Expense {
        $expenseDate = $expenseDate ?? now()->toDateString();

        return DB::transaction(function () use ($branch, $poster, $accountCode, $category, $description, $amountMyr, $expenseDate) {
            $lockedBranch = Branch::where('id', $branch->id)->lockForUpdate()->firstOrFail();

            if ($this->mathService->compare($amountMyr, '0') <= 0) {
                throw new AccountingPeriodException('Expense amount must be positive');
            }

            if ($this->mathService->compare((string) $lockedBranch->petty_cash_myr, $amountMyr) < 0) {
                throw new InsufficientPettyCashException(
                    (string) $lockedBranch->petty_cash_myr,
                    $amountMyr
                );
            }

            $journal = $this->accountingService->createJournalEntry(
                [
                    [
                        'account_code' => $accountCode,
                        'debit' => $amountMyr,
                        'credit' => '0',
                        'description' => $description,
                    ],
                    [
                        'account_code' => $this->accountMappingService->code(AccountMappingKey::CashPetty),
                        'debit' => '0',
                        'credit' => $amountMyr,
                        'description' => $description,
                    ],
                ],
                'Expense',
                null,
                "Petty cash expense — {$category}: {$description}",
                $expenseDate,
                $poster->id,
                $lockedBranch->id
            );

            $expense = Expense::create([
                'branch_id' => $lockedBranch->id,
                'account_code' => $accountCode,
                'category' => $category,
                'description' => $description,
                'amount_myr' => $amountMyr,
                'expense_date' => $expenseDate,
                'journal_entry_id' => $journal->id,
                'created_by' => $poster->id,
            ]);

            $lockedBranch->petty_cash_myr = $this->mathService->subtract(
                (string) $lockedBranch->petty_cash_myr,
                $amountMyr
            );
            $lockedBranch->save();

            $this->auditService->log(
                'expense_posted',
                $poster->id,
                'Expense',
                $expense->id,
                [],
                [
                    'branch_id' => $lockedBranch->id,
                    'account_code' => $accountCode,
                    'amount_myr' => $amountMyr,
                    'journal_entry_id' => $journal->id,
                ]
            );

            return $expense;
        });
    }

    /**
     * Top up a branch petty-cash float (Dr petty cash, Cr cash on hand).
     * Admin-only — funding moves company money into branch floats.
     */
    public function fundPettyCash(Branch $branch, User $poster, string $amountMyr, ?string $description = null): JournalEntry
    {
        return DB::transaction(function () use ($branch, $poster, $amountMyr, $description) {
            $lockedBranch = Branch::where('id', $branch->id)->lockForUpdate()->firstOrFail();

            if ($this->mathService->compare($amountMyr, '0') <= 0) {
                throw new AccountingPeriodException('Funding amount must be positive');
            }

            $journal = $this->accountingService->createJournalEntry(
                [
                    [
                        'account_code' => $this->accountMappingService->code(AccountMappingKey::CashPetty),
                        'debit' => $amountMyr,
                        'credit' => '0',
                        'description' => $description ?? 'Petty cash funding',
                    ],
                    [
                        'account_code' => $this->accountMappingService->code(AccountMappingKey::CashMyr),
                        'debit' => '0',
                        'credit' => $amountMyr,
                        'description' => $description ?? 'Petty cash funding',
                    ],
                ],
                'PettyCashFunding',
                null,
                $description ?? "Petty cash funding — {$lockedBranch->name}",
                now()->toDateString(),
                $poster->id,
                $lockedBranch->id
            );

            $lockedBranch->petty_cash_myr = $this->mathService->add(
                (string) $lockedBranch->petty_cash_myr,
                $amountMyr
            );
            $lockedBranch->save();

            $this->auditService->log(
                'petty_cash_funded',
                $poster->id,
                'JournalEntry',
                $journal->id,
                [],
                [
                    'branch_id' => $lockedBranch->id,
                    'amount_myr' => $amountMyr,
                ]
            );

            return $journal;
        });
    }
}
