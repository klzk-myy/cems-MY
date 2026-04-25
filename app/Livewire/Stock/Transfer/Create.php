<?php

namespace App\Livewire\Stock\Transfer;

use App\Enums\UserRole;
use App\Livewire\BaseComponent;
use App\Models\Branch;
use App\Models\Currency;
use App\Services\MathService;
use App\Services\StockTransferService;
use Illuminate\Support\Facades\Auth;

class Create extends BaseComponent
{
    public string $transferDate = '';

    public string $sourceBranchId = '';

    public string $destinationBranchId = '';

    public string $type = 'Standard';

    public string $notes = '';

    /** @var array<int, array{currency_code: string, quantity: string, rate: string, value_myr: string}> */
    public array $items = [];

    public array $availableBranches = [];

    public array $availableCurrencies = [];

    public string $totalValue = '0.00';

    public array $errors = [];

    protected MathService $mathService;

    public function mount(): void
    {
        $this->mathService = new MathService;
        $this->transferDate = now()->toDateString();
        $this->loadBranches();
        $this->loadCurrencies();
        $this->addItem();
    }

    protected function loadBranches(): void
    {
        $user = Auth::user();

        $branchQuery = Branch::active();

        // Non-admin users can only create transfers from their own branch
        if (! $user->role->canManageAllBranches()) {
            $branchQuery->where('id', $user->branch_id);
        }

        $this->availableBranches = $branchQuery->orderBy('name')
            ->get()
            ->map(fn ($branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
            ])
            ->toArray();
    }

    protected function loadCurrencies(): void
    {
        $this->availableCurrencies = Currency::where('is_active', true)
            ->orderBy('code')
            ->get()
            ->map(fn ($currency) => [
                'code' => $currency->code,
                'name' => $currency->name,
            ])
            ->toArray();
    }

    public function addItem(): void
    {
        $this->items[] = [
            'currency_code' => '',
            'quantity' => '',
            'rate' => '',
            'value_myr' => '',
        ];
    }

    public function removeItem(int $index): void
    {
        if (count($this->items) > 1) {
            unset($this->items[$index]);
            $this->items = array_values($this->items);
            $this->calculateTotal();
        }
    }

    public function updatedItems(): void
    {
        $this->calculateTotal();
    }

    public function calculateTotal(): void
    {
        $total = '0';

        foreach ($this->items as $item) {
            if (! empty($item['quantity']) && ! empty($item['rate'])) {
                $itemValue = $this->mathService->multiply(
                    (string) $item['quantity'],
                    (string) $item['rate']
                );
                $total = $this->mathService->add($total, $itemValue);
            }
        }

        $this->totalValue = $total;
    }

    protected function validateTransfer(): bool
    {
        $this->errors = [];

        // Validate required fields
        if (empty($this->transferDate)) {
            $this->errors['transferDate'] = 'Transfer date is required';
        }

        if (empty($this->sourceBranchId)) {
            $this->errors['sourceBranchId'] = 'Source branch is required';
        }

        if (empty($this->destinationBranchId)) {
            $this->errors['destinationBranchId'] = 'Destination branch is required';
        }

        if ($this->sourceBranchId === $this->destinationBranchId) {
            $this->errors['destinationBranchId'] = 'Source and destination branches cannot be the same';
        }

        // Validate items
        $hasValidItem = false;
        foreach ($this->items as $index => $item) {
            if (empty($item['currency_code'])) {
                $this->errors["items.{$index}.currency_code"] = 'Currency is required';
            }

            if (empty($item['quantity']) || (float) $item['quantity'] <= 0) {
                $this->errors["items.{$index}.quantity"] = 'Quantity must be a positive number';
            }

            if (empty($item['rate']) || (float) $item['rate'] <= 0) {
                $this->errors["items.{$index}.rate"] = 'Rate must be a positive number';
            }

            if (! empty($item['currency_code']) && ! empty($item['quantity']) && ! empty($item['rate'])) {
                $hasValidItem = true;
            }
        }

        if (! $hasValidItem) {
            $this->errors['items'] = 'At least one valid item is required';
        }

        return empty($this->errors);
    }

    public function save(): void
    {
        if (! $this->validateTransfer()) {
            return;
        }

        $user = Auth::user();

        // Check role permission
        if ($user->role !== UserRole::Manager && $user->role !== UserRole::Admin) {
            $this->error('Only managers can create stock transfers');

            return;
        }

        try {
            $sourceBranch = Branch::find($this->sourceBranchId);
            $destinationBranch = Branch::find($this->destinationBranchId);

            $transferData = [
                'transfer_date' => $this->transferDate,
                'source_branch_name' => $sourceBranch->name,
                'destination_branch_name' => $destinationBranch->name,
                'type' => $this->type,
                'notes' => $this->notes ?: null,
                'items' => array_map(fn ($item) => [
                    'currency_code' => $item['currency_code'],
                    'quantity' => $item['quantity'],
                    'rate' => $item['rate'],
                    'value_myr' => $this->mathService->multiply($item['quantity'], $item['rate']),
                ], $this->items),
            ];

            $stockTransferService = new StockTransferService($user);
            $transfer = $stockTransferService->createRequest($transferData);

            $this->success('Transfer request created successfully');

            $this->redirect(route('stock-transfers.show', $transfer->id));
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
        } catch (\Exception $e) {
            $this->error('Failed to create transfer: '.$e->getMessage());
        }
    }

    public function cancel(): void
    {
        $this->redirect(route('stock-transfers.index'));
    }

    public function render()
    {
        return view('livewire.stock.transfer.create');
    }
}
