<?php

namespace App\Http\Requests;

use App\Enums\AmlRuleType;
use Illuminate\Foundation\Http\FormRequest;

class StoreAmlRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rule_code' => 'required|string|max:50|unique:aml_rules,rule_code',
            'rule_name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'rule_type' => 'required|in:'.implode(',', AmlRuleType::values()),
            'conditions' => 'required|array',
            'action' => 'required|in:flag,hold,block',
            'risk_score' => 'required|integer|min:0|max:100',
            'is_active' => 'boolean',
        ];
    }
}
