<?php

namespace App\Livewire\Compliance\Rules;

use App\Enums\AmlRuleType;
use App\Livewire\BaseComponent;
use App\Models\AmlRule;
use Illuminate\View\View;

class Form extends BaseComponent
{
    public ?AmlRule $rule = null;

    public bool $isEditing = false;

    // Form fields
    public string $ruleCode = '';

    public string $ruleName = '';

    public string $description = '';

    public string $ruleType = '';

    public string $conditions = '{}';

    public string $action = 'flag';

    public int $riskScore = 0;

    public bool $isActive = true;

    protected $rules = [
        'ruleCode' => 'required|string|max:50',
        'ruleName' => 'required|string|max:255',
        'description' => 'nullable|string|max:1000',
        'ruleType' => 'required|in:velocity,structuring,amount_threshold,frequency,geographic',
        'conditions' => 'required|json',
        'action' => 'required|in:flag,hold,block',
        'riskScore' => 'required|integer|min:0|max:100',
        'isActive' => 'boolean',
    ];

    public function mount(?AmlRule $rule = null): void
    {
        if ($rule && $rule->exists) {
            $this->rule = $rule;
            $this->isEditing = true;
            $this->loadRuleData();
        }
    }

    public function loadRuleData(): void
    {
        if (! $this->rule) {
            return;
        }

        $this->ruleCode = $this->rule->rule_code ?? '';
        $this->ruleName = $this->rule->rule_name ?? '';
        $this->description = $this->rule->description ?? '';
        $this->ruleType = is_object($this->rule->rule_type) ? $this->rule->rule_type->value : ($this->rule->rule_type ?? '');
        $this->conditions = json_encode($this->rule->conditions ?? [], JSON_PRETTY_PRINT);
        $this->action = $this->rule->action ?? 'flag';
        $this->riskScore = $this->rule->risk_score ?? 0;
        $this->isActive = $this->rule->is_active ?? true;
    }

    public function loadExample(string $type): void
    {
        $examples = [
            'velocity' => [
                'rule_code' => 'VEL-001',
                'name' => 'High Velocity Alert',
                'description' => 'Detects excessive transaction volume within 24 hour rolling window',
                'type' => 'velocity',
                'conditions' => [
                    'window_hours' => 24,
                    'max_transactions' => 10,
                    'cumulative_threshold' => 50000,
                ],
                'action' => 'flag',
                'risk_score' => 25,
                'is_active' => true,
            ],
            'structuring' => [
                'rule_code' => 'STR-001',
                'name' => 'Structuring Detection (Daily)',
                'description' => 'Detects transactions broken into amounts below RM10k reporting threshold over 24 hours (BNM Compliance Rule 3.2.1)',
                'type' => 'structuring',
                'conditions' => [
                    'window_days' => 1,
                    'min_transaction_count' => 3,
                    'aggregate_threshold' => 45000,
                    'max_individual_amount' => 9999,
                ],
                'action' => 'hold',
                'risk_score' => 40,
                'is_active' => true,
            ],
            'amount' => [
                'rule_code' => 'AMT-001',
                'name' => 'Large Transaction Alert',
                'description' => 'Triggers on all transactions equal or above RM50,000 threshold',
                'type' => 'amount_threshold',
                'conditions' => [
                    'min_amount' => 50000,
                    'currency' => 'MYR',
                ],
                'action' => 'flag',
                'risk_score' => 20,
                'is_active' => true,
            ],
            'frequency' => [
                'rule_code' => 'FREQ-001',
                'name' => 'High Frequency Alert',
                'description' => 'Detects rapid repeated transactions within short time window',
                'type' => 'frequency',
                'conditions' => [
                    'window_hours' => 0.25,
                    'max_transactions' => 5,
                ],
                'action' => 'flag',
                'risk_score' => 25,
                'is_active' => true,
            ],
            'geographic' => [
                'rule_code' => 'GEO-001',
                'name' => 'FATF High-Risk Countries',
                'description' => 'Flags transactions from customers nationals of FATF high risk jurisdictions',
                'type' => 'geographic',
                'conditions' => [
                    'countries' => ['IR', 'KP', 'SY', 'MM', 'AF'],
                    'match_field' => 'customer_nationality',
                ],
                'action' => 'hold',
                'risk_score' => 50,
                'is_active' => true,
            ],
        ];

        if (isset($examples[$type])) {
            $example = $examples[$type];
            $this->ruleCode = $example['rule_code'];
            $this->ruleName = $example['name'];
            $this->description = $example['description'];
            $this->ruleType = $example['type'];
            $this->conditions = json_encode($example['conditions'], JSON_PRETTY_PRINT);
            $this->action = $example['action'];
            $this->riskScore = $example['risk_score'];
            $this->isActive = $example['is_active'];
        }
    }

    public function save(): void
    {
        $this->validate();

        $conditionsArray = json_decode($this->conditions, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Invalid JSON in conditions field');

            return;
        }

        $data = [
            'rule_code' => $this->ruleCode,
            'rule_name' => $this->ruleName,
            'description' => $this->description,
            'rule_type' => $this->ruleType,
            'conditions' => $conditionsArray,
            'action' => $this->action,
            'risk_score' => $this->riskScore,
            'is_active' => $this->isActive,
            'created_by' => auth()->id(),
        ];

        try {
            if ($this->isEditing && $this->rule) {
                $this->rule->update($data);
                $this->success('Rule updated successfully');
            } else {
                AmlRule::create($data);
                $this->success('Rule created successfully');
            }
            $this->redirect(route('compliance.rules.index'));
        } catch (\Exception $e) {
            $this->error('Failed to save rule: '.$e->getMessage());
        }
    }

    public function render(): View
    {
        return view('livewire.compliance.rules.form', [
            'ruleTypes' => AmlRuleType::cases(),
            'actions' => ['flag', 'hold', 'block'],
        ]);
    }
}
