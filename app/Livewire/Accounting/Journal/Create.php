<?php

namespace App\Livewire\Accounting\Journal;

use App\Livewire\BaseComponent;
use App\Models\ChartOfAccount;
use App\Services\AccountingService;
use App\Services\MathService;
use Illuminate\Routing\Redirector;
use Illuminate\View\View;
use Livewire\Attributes\Validate;

class Create extends BaseComponent
{
    #[Validate('required|date')]
    public string $entryDate = '';

    #[Validate('required|string|max:255')]
    public string $description = '';

    /**
     * Journal entry lines.
     * Each line has: account_code, debit, credit, description
     */
    public array $lines = [];

    /**
     * Available accounts for dropdown.
     */
    public array $accounts = [];

    /**
     * Track if entry is balanced.
     */
    public bool $isBalanced = true;

    /**
     * Total debits.
     */
    public string $totalDebits = '0';

    /**
     * Total credits.
     */
    public string $totalCredits = '0';

    /**
     * Validation errors for lines.
     */
    public array $lineErrors = [];

    public function mount(): void
    {
        $this->entryDate = now()->toDateString();
        $this->loadAccounts();
        $this->addLine();
        $this->addLine();
    }

    protected function loadAccounts(): void
    {
        $this->accounts = ChartOfAccount::where('is_active', true)
            ->where('allow_journal', true)
            ->orderBy('account_code')
            ->get()
            ->map(function ($account) {
                return [
                    'code' => $account->account_code,
                    'name' => $account->account_name,
                    'type' => $account->account_type,
                    'label' => $account->account_code.' - '.$account->account_name,
                ];
            })
            ->toArray();
    }

    public function addLine(): void
    {
        $this->lines[] = [
            'account_code' => '',
            'debit' => '',
            'credit' => '',
            'description' => '',
        ];
    }

    public function removeLine(int $index): void
    {
        if (count($this->lines) > 2) {
            unset($this->lines[$index]);
            $this->lines = array_values($this->lines);
            $this->calculateTotals();
        }
    }

    public function updatedLines(): void
    {
        $this->calculateTotals();
    }

    public function updated(string $property): void
    {
        if (str_starts_with($property, 'lines.')) {
            $this->calculateTotals();
        }
    }

    protected function calculateTotals(): void
    {
        $mathService = new MathService;
        $this->totalDebits = '0';
        $this->totalCredits = '0';
        $this->lineErrors = [];

        foreach ($this->lines as $index => $line) {
            $debit = $line['debit'] ?? '';
            $credit = $line['credit'] ?? '';

            // Ensure only one of debit or credit is filled
            if (! empty($debit) && ! empty($credit)) {
                $this->lineErrors[$index] = 'Only debit OR credit can be filled, not both';
            }

            if (! empty($debit)) {
                $this->totalDebits = $mathService->add($this->totalDebits, $debit);
            }
            if (! empty($credit)) {
                $this->totalCredits = $mathService->add($this->totalCredits, $credit);
            }
        }

        $this->isBalanced = $mathService->compare($this->totalDebits, $this->totalCredits) === 0;
    }

    public function autoBalance(): void
    {
        $mathService = new MathService;
        $diff = $mathService->subtract($this->totalDebits, $this->totalCredits);

        if ($mathService->compare($diff, '0') > 0) {
            // Debits > Credits - add difference to credit of last line
            $lastIndex = count($this->lines) - 1;
            if ($lastIndex >= 0) {
                $currentCredit = $this->lines[$lastIndex]['credit'] ?? '0';
                $newCredit = $mathService->add($currentCredit ?: '0', $diff);
                $this->lines[$lastIndex]['credit'] = $newCredit;
                $this->calculateTotals();
            }
        } elseif ($mathService->compare($diff, '0') < 0) {
            // Credits > Debits - add difference to debit of last line
            $lastIndex = count($this->lines) - 1;
            if ($lastIndex >= 0) {
                $currentDebit = $this->lines[$lastIndex]['debit'] ?? '0';
                $newDebit = $mathService->add($currentDebit ?: '0', $mathService->abs($diff));
                $this->lines[$lastIndex]['debit'] = $newDebit;
                $this->calculateTotals();
            }
        }
    }

    protected function validateLines(): bool
    {
        $this->lineErrors = [];
        $hasErrors = false;

        if (empty($this->description)) {
            $this->addError('description', 'Description is required');
            $hasErrors = true;
        }

        if (empty($this->entryDate)) {
            $this->addError('entryDate', 'Entry date is required');
            $hasErrors = true;
        }

        // Check at least 2 lines
        $validLines = 0;
        foreach ($this->lines as $index => $line) {
            $hasDebit = ! empty($line['debit']);
            $hasCredit = ! empty($line['credit']);
            $hasAccount = ! empty($line['account_code']);

            if ($hasDebit || $hasCredit) {
                if (! $hasAccount) {
                    $this->lineErrors[$index] = 'Account is required';
                    $hasErrors = true;
                }
                if ($hasDebit && $hasCredit) {
                    $this->lineErrors[$index] = 'Only debit OR credit can be filled';
                    $hasErrors = true;
                }
                $validLines++;
            }
        }

        if ($validLines < 2) {
            $this->addError('lines', 'At least two lines with amounts are required');
            $hasErrors = true;
        }

        if (! $this->isBalanced) {
            $this->addError('balance', 'Debits must equal credits');
            $hasErrors = true;
        }

        return ! $hasErrors;
    }

    public function save(): ?Redirector
    {
        $this->lineErrors = [];

        if (! $this->validateLines()) {
            return null;
        }

        try {
            $accountingService = app(AccountingService::class);

            // Prepare lines for the service
            $entryLines = [];
            foreach ($this->lines as $line) {
                if (! empty($line['account_code']) && (! empty($line['debit']) || ! empty($line['credit']))) {
                    $entryLines[] = [
                        'account_code' => $line['account_code'],
                        'debit' => $line['debit'] ?: '0',
                        'credit' => $line['credit'] ?: '0',
                        'description' => $line['description'] ?: null,
                    ];
                }
            }

            $accountingService->createJournalEntry(
                lines: $entryLines,
                referenceType: 'Manual',
                description: $this->description,
                entryDate: $this->entryDate,
            );

            $this->success('Journal entry created successfully!');

            return $this->redirect(route('accounting.journal'));
        } catch (\Exception $e) {
            $this->error('Failed to create journal entry: '.$e->getMessage());

            return null;
        }
    }

    public function saveDraft(): ?Redirector
    {
        // For now, drafts are saved the same way
        // In a full implementation, you'd have a separate draft status
        return $this->save();
    }

    public function render(): View
    {
        return view('livewire.accounting.journal.create');
    }
}
