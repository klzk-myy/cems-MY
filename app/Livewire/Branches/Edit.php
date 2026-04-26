<?php

namespace App\Livewire\Branches;

use App\Livewire\BaseComponent;
use App\Models\Branch;
use Illuminate\View\View;

class Edit extends BaseComponent
{
    public Branch $branch;

    public string $code = '';

    public string $name = '';

    public string $type = '';

    public ?string $address = null;

    public ?string $city = null;

    public ?string $state = null;

    public ?string $postalCode = null;

    public ?string $country = 'Malaysia';

    public ?string $phone = null;

    public ?string $email = null;

    public bool $isActive = false;

    public bool $isMain = false;

    public ?int $parentId = null;

    public array $branchTypes = [];

    public array $parentBranches = [];

    public function mount(Branch $branch): void
    {
        $this->branch = $branch;
        $this->code = $branch->code;
        $this->name = $branch->name;
        $this->type = $branch->type;
        $this->address = $branch->address;
        $this->city = $branch->city;
        $this->state = $branch->state;
        $this->postalCode = $branch->postal_code;
        $this->country = $branch->country ?? 'Malaysia';
        $this->phone = $branch->phone;
        $this->email = $branch->email;
        $this->isActive = $branch->is_active;
        $this->isMain = $branch->is_main;
        $this->parentId = $branch->parent_id;
        $this->branchTypes = [
            'head_office' => 'Head Office',
            'branch' => 'Branch',
            'sub_branch' => 'Sub-Branch',
        ];
        $this->loadParentBranches();
    }

    protected function loadParentBranches(): void
    {
        $this->parentBranches = Branch::where('is_active', true)
            ->where('id', '!=', $this->branch->id)
            ->orderBy('code')
            ->get()
            ->map(function ($branch) {
                return [
                    'id' => $branch->id,
                    'code' => $branch->code,
                    'name' => $branch->name,
                ];
            })
            ->toArray();
    }

    protected function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'unique:branches,code,'.$this->branch->id],
            'name' => 'required|string|max:255',
            'type' => 'required|in:head_office,branch,sub_branch',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postalCode' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:100',
            'isActive' => 'boolean',
            'isMain' => 'boolean',
            'parentId' => 'nullable|exists:branches,id',
        ];
    }

    public function save(): mixed
    {
        $this->validate();

        try {
            if ($this->isMain && ! $this->branch->is_main) {
                Branch::where('is_main', true)->update(['is_main' => false]);
            }

            $this->branch->update([
                'code' => $this->code,
                'name' => $this->name,
                'type' => $this->type,
                'address' => $this->address,
                'city' => $this->city,
                'state' => $this->state,
                'postal_code' => $this->postalCode,
                'country' => $this->country,
                'phone' => $this->phone,
                'email' => $this->email,
                'is_active' => $this->isActive,
                'is_main' => $this->isMain,
                'parent_id' => $this->parentId,
            ]);

            $this->success("Branch {$this->branch->code} - {$this->branch->name} updated successfully!");

            return $this->redirect(route('branches.show', $this->branch));
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return null;
        }
    }

    public function render(): View
    {
        return view('livewire.branches.edit');
    }
}
