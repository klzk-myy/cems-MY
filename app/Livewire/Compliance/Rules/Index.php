<?php

namespace App\Livewire\Compliance\Rules;

use App\Enums\AmlRuleType;
use App\Livewire\BaseComponent;
use App\Models\AmlRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\WithPagination;

class Index extends BaseComponent
{
    use WithPagination;

    public string $search = '';

    public ?string $type = '';

    public ?string $status = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'type' => ['except' => ''],
        'status' => ['except' => ''],
    ];

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->type = '';
        $this->status = '';
        $this->resetPage();
    }

    public function toggleRule(AmlRule $rule): void
    {
        try {
            $rule->update(['is_active' => ! $rule->is_active]);
            $status = $rule->is_active ? 'enabled' : 'disabled';
            $this->success("Rule {$rule->rule_name} has been {$status}");
        } catch (\Exception $e) {
            $this->error('Failed to toggle rule: '.$e->getMessage());
        }
    }

    public function getRules(): LengthAwarePaginator
    {
        $query = AmlRule::query();

        // Search filter
        if (! empty($this->search)) {
            $search = $this->search;
            $query->where(function (Builder $q) use ($search) {
                $q->where('rule_name', 'like', "%{$search}%")
                    ->orWhere('rule_code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Type filter
        if (! empty($this->type) && AmlRuleType::tryFrom($this->type) !== null) {
            $query->where('rule_type', $this->type);
        }

        // Status filter
        if ($this->status !== '') {
            $isActive = $this->status === 'active';
            $query->where('is_active', $isActive);
        }

        return $query
            ->orderBy('is_active', 'desc')
            ->orderBy('rule_name')
            ->paginate(20);
    }

    public function getSummary(): array
    {
        return [
            'total' => AmlRule::count(),
            'active' => AmlRule::where('is_active', true)->count(),
            'inactive' => AmlRule::where('is_active', false)->count(),
        ];
    }

    public function render(): View
    {
        return view('livewire.compliance.rules.index', [
            'rules' => $this->getRules(),
            'summary' => $this->getSummary(),
            'ruleTypes' => AmlRuleType::cases(),
        ]);
    }
}
