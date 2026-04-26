<?php

namespace App\Livewire\Branches;

use App\Livewire\BaseComponent;
use App\Models\Branch;
use Illuminate\View\View;

class Create extends BaseComponent
{
    public string $code = '';

    public string $name = '';

    public string $type = 'branch';

    public ?string $address = null;

    public ?string $city = null;

    public ?string $state = null;

    public ?string $postalCode = null;

    public ?string $country = 'Malaysia';

    public ?string $phone = null;

    public ?string $email = null;

    public bool $isMain = false;

    public ?int $parentId = null;

    public array $branchTypes = [];

    public array $parentBranches = [];

    public function mount(): void
    {
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
            'code' => 'required|string|max:20|unique:branches,code',
            'name' => 'required|string|max:255',
            'type' => 'required|in:head_office,branch,sub_branch',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postalCode' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:100',
            'isMain' => 'boolean',
            'parentId' => 'nullable|exists:branches,id',
        ];
    }

    public function save(): mixed
    {
        $this->validate();

        try {
            if ($this->isMain) {
                Branch::where('is_main', true)->update(['is_main' => false]);
            }

            $branch = Branch::create([
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
                'is_active' => true,
                'is_main' => $this->isMain,
                'parent_id' => $this->parentId,
            ]);

            $this->success("Branch {$branch->code} - {$branch->name} created successfully!");

            return $this->redirect(route('branches.index'));
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return null;
        }
    }

    public function render(): View
    {
        return view('livewire.branches.create');
    }
}
