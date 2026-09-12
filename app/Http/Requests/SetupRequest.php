<?php

namespace App\Http\Requests;

use App\Rules\PasswordComplexityRule;

class SetupRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $step = $this->input('step', $this->route()->getActionMethod());

        if (is_numeric($step)) {
            return $this->stepRules((int) $step);
        }

        return match ($step) {
            'quickSetup' => $this->quickSetupRules(),
            'step1CompanyInfo' => $this->step1Rules(),
            'step2AdminUser' => $this->step2Rules(),
            'step3Currencies' => $this->step3Rules(),
            'step4ExchangeRates' => $this->step4Rules(),
            'step5InitialStock' => $this->step5Rules(),
            'step6OpeningBalance' => $this->step6Rules(),
            default => [],
        };
    }

    private function stepRules(int $step): array
    {
        return match ($step) {
            1 => $this->step1Rules(),
            2 => $this->step2Rules(),
            3 => $this->step3Rules(),
            4 => $this->step4Rules(),
            5 => $this->step5Rules(),
            6 => $this->step6Rules(),
            default => [],
        };
    }

    private function quickSetupRules(): array
    {
        return [
            'business_name' => 'required|string|max:255',
            'admin_email' => 'required|email',
            'admin_password' => ['required', new PasswordComplexityRule],
            'base_currency' => 'required|string|size:3',
            'setup_exchange_rates' => 'boolean',
            'setup_branch_pools' => 'boolean',
        ];
    }

    private function step1Rules(): array
    {
        return [
            'business_name' => 'required|string|max:255',
            'business_address' => 'nullable|string',
            'business_phone' => 'nullable|string',
            'business_email' => 'nullable|email',
        ];
    }

    private function step2Rules(): array
    {
        return [
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|unique:users,email',
            'admin_password' => ['required', 'confirmed', new PasswordComplexityRule],
        ];
    }

    private function step3Rules(): array
    {
        return [
            'base_currency' => 'required|string|size:3',
            'active_currencies' => 'required|array|min:1',
            'active_currencies.*' => 'string|size:3',
            'custom_currency_code' => 'nullable|string|alpha|size:3',
            'custom_currency_name' => 'nullable|string|max:100|required_with:custom_currency_code',
            'custom_currency_symbol' => 'nullable|string|max:10',
        ];
    }

    private function step4Rules(): array
    {
        $rules = [
            'use_default_rates' => 'boolean',
            'custom_rates' => 'nullable|array',
            'custom_rates.*.buy' => 'nullable|numeric|min:0.0001',
            'custom_rates.*.sell' => 'nullable|numeric|min:0.0001',
        ];

        // A custom "other" currency has no seeded rate — without one it can
        // never be traded, so its buy/sell rates are mandatory here.
        $customCode = strtoupper(trim((string) session('setup.currencies.custom_currency_code', '')));
        if ($customCode !== '') {
            $rules["custom_rates.{$customCode}.buy"] = 'required|numeric|min:0.0001';
            $rules["custom_rates.{$customCode}.sell"] = 'required|numeric|min:0.0001';
        }

        return $rules;
    }

    private function step5Rules(): array
    {
        return [
            'initial_myr_cash' => 'required|numeric|min:0',
            'initial_stock' => 'nullable|array',
            'initial_stock.*' => 'nullable|numeric|min:0',
            'initial_foreign_cash' => 'nullable|array',
            'initial_foreign_cash.*' => 'nullable|numeric|min:0',
        ];
    }

    private function step6Rules(): array
    {
        return [
            'opening_balance_myr' => 'required|numeric|min:0',
            'opening_balance_foreign' => 'nullable|array',
            'opening_balance_foreign.*' => 'nullable|numeric|min:0',
        ];
    }
}
