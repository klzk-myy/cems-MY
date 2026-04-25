<?php

namespace App\Livewire\Accounting;

use App\Livewire\BaseComponent;
use App\Services\BudgetService;
use Illuminate\View\View;

class BudgetReport extends BaseComponent
{
    public array $report = [];

    public array $unbudgetedAccounts = [];

    public string $periodCode = '';

    public array $budgetItems = [];

    public bool $showEditForm = false;

    public int $editingBudgetId = 0;

    public string $editAmount = '';

    protected BudgetService $budgetService;

    public function mount(): void
    {
        $this->periodCode = now()->format('Y-m');
        $this->budgetService = app(BudgetService::class);
        $this->loadBudgetReport();
    }

    protected function loadBudgetReport(): void
    {
        $reportData = $this->budgetService->getBudgetReport($this->periodCode);
        $this->report = $reportData['items'] ?? [];

        $unbudgeted = $this->budgetService->getAccountsWithoutBudget($this->periodCode);
        $this->unbudgetedAccounts = $unbudgeted ?? [];
    }

    public function updatedPeriodCode(): void
    {
        $this->loadBudgetReport();
    }

    public function render(): View
    {
        return view('livewire.accounting.budget');
    }
}
