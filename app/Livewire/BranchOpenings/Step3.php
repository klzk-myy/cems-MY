<?php

namespace App\Livewire\BranchOpenings;

use App\Livewire\BaseComponent;
use App\Models\Branch;
use App\Services\AccountingService;
use App\Services\MathService;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class Step3 extends BaseComponent
{
    public Branch $branch;

    public string $amount = '';

    public string $reference = '';

    public string $error = '';

    public string $totalPoolAmount = '0';

    public function mount(Branch $branch): void
    {
        $this->branch = $branch;
        $this->totalPoolAmount = $this->calculateTotalPoolAmount();
    }

    public function calculateTotalPoolAmount(): string
    {
        $total = '0';
        $mathService = app(MathService::class);

        foreach ($this->branch->branchPools as $pool) {
            $total = $mathService->add($total, $pool->available_balance ?? '0');
        }

        return $total;
    }

    public function processStep3(AccountingService $accountingService): mixed
    {
        if (empty($this->amount) || ! is_numeric($this->amount) || floatval($this->amount) <= 0) {
            $this->error = 'Please enter a valid amount.';

            return null;
        }

        $reference = $this->reference ?: "Opening balance for {$this->branch->code}";

        $reference = $this->reference ?: "Opening balance for {$this->branch->code}";

        try {
            DB::transaction(function () use ($accountingService, $reference) {
                $entry = $accountingService->createJournalEntry([
                    [
                        'account_code' => '1010',
                        'debit' => $this->amount,
                        'credit' => '0',
                        'description' => "Initial capital - {$this->branch->name}",
                    ],
                    [
                        'account_code' => '3000',
                        'debit' => '0',
                        'credit' => $this->amount,
                        'description' => "Owner's capital contribution - {$this->branch->name}",
                    ],
                ], 'Opening Balance', null, $reference, now()->toDateString(), auth()->id());

                session(['branch_opening_entry_id' => $entry->id]);
            });

            $this->success('Opening balance journal entry created successfully!');

            return $this->redirect(route('branches.open.complete', ['branch' => $this->branch->id]));
        } catch (\Exception $e) {
            $this->error = 'Failed to create journal entry: '.$e->getMessage();

            return null;
        }
    }

    public function render(): View
    {
        return view('livewire.branch-openings.step3');
    }
}
