<?php

namespace App\Livewire\BranchOpenings;

use App\Livewire\BaseComponent;
use App\Models\Branch;
use Illuminate\View\View;

class Step1 extends BaseComponent
{
    public string $code = '';

    public string $name = '';

    public string $type = '';

    public string $address = '';

    public string $city = '';

    public string $state = '';

    public string $postal_code = '';

    public string $country = 'Malaysia';

    public string $phone = '';

    public string $email = '';

    public ?int $parent_id = null;

    public bool $is_main = false;

    public string $error = '';

    public array $branchTypes = [];

    public array $parentBranches = [];

    public function mount(): void
    {
        $this->branchTypes = [
            'head_office' => 'Head Office',
            'branch' => 'Branch',
            'sub_branch' => 'Sub-Branch',
        ];

        $this->parentBranches = Branch::where('is_active', true)
            ->orderBy('code')
            ->get()
            ->map(fn ($b) => ['id' => $b->id, 'code' => $b->code, 'name' => $b->name])
            ->toArray();
    }

    public function processStep1(): mixed
    {
        if (empty($this->code) || empty($this->name) || empty($this->type)) {
            $this->error = 'Code, name, and type are required.';

            return null;
        }

        if (! in_array($this->type, array_keys($this->branchTypes))) {
            $this->error = 'Invalid branch type.';

            return null;
        }

        // Check unique code
        if (Branch::where('code', $this->code)->exists()) {
            $this->error = 'Branch code already exists.';

            return null;
        }

        if ($this->is_main) {
            Branch::where('is_main', true)->update(['is_main' => false]);
        }

        $branch = Branch::create([
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'address' => $this->address ?: null,
            'city' => $this->city ?: null,
            'state' => $this->state ?: null,
            'postal_code' => $this->postal_code ?: null,
            'country' => $this->country ?: 'Malaysia',
            'phone' => $this->phone ?: null,
            'email' => $this->email ?: null,
            'is_active' => true,
            'is_main' => $this->is_main,
            'parent_id' => $this->parent_id ?: null,
        ]);

        $this->success('Branch created successfully!');

        return $this->redirect(route('branches.open.step2', ['branch' => $branch->id]));
    }

    public function render(): View
    {
        return view('livewire.branch-openings.step1');
    }
}
