<?php

namespace App\Livewire\BranchOpenings;

use App\Livewire\BaseComponent;
use App\Models\Branch;
use App\Models\Currency;
use App\Services\BranchPoolService;
use Illuminate\View\View;

class Step2 extends BaseComponent
{
    public Branch $branch;

    public array $currencies = [];

    public array $poolAmounts = [];

    public string $error = '';

    public function mount(Branch $branch): void
    {
        $this->branch = $branch;
        $this->currencies = Currency::where('is_active', true)->get()->toArray();

        $existingPools = $branch->branchPools()->get()->pluck('available_balance', 'currency_code')->toArray();
        foreach ($this->currencies as $currency) {
            $this->poolAmounts[$currency['code']] = $existingPools[$currency['code']] ?? '0';
        }
    }

    public function processStep2(): mixed
    {
        $poolService = app(BranchPoolService::class);

        foreach ($this->poolAmounts as $currencyCode => $amount) {
            if ($amount && is_numeric($amount) && floatval($amount) > 0) {
                $poolService->replenish($this->branch, $currencyCode, $amount, auth()->id());
            }
        }

        $this->success('Currency pools initialized successfully!');

        return $this->redirect(route('branches.open.step3', ['branch' => $this->branch->id]));
    }

    public function render(): View
    {
        return view('livewire.branch-openings.step2');
    }
}
