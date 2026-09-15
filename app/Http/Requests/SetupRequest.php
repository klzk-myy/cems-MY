<?php

namespace App\Http\Requests;

use App\Models\Currency;
use App\Rules\PasswordRules;
use App\Services\System\SetupService;

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
            'admin_password' => PasswordRules::forNew(confirmed: false),
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
            'admin_password' => PasswordRules::forNew(),
        ];
    }

    private function step3Rules(): array
    {
        return [
            'base_currency' => 'required|string|size:3',
            'active_currencies' => 'required|array|min:1',
            'active_currencies.*' => 'string|size:3',
            'custom_currencies' => 'nullable|array|max:25',
            'custom_currencies.*.code' => 'nullable|string|alpha|size:3',
            'custom_currencies.*.name' => 'nullable|string|max:100|required_with:custom_currencies.*.code',
            'custom_currencies.*.symbol' => 'nullable|string|max:10',
            // Legacy single-field keys: kept nullable so a stale cached form
            // still validates and folds into custom_currencies.
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

        // Custom "other" currencies have no seeded rate — without one they can
        // never be traded, so buy/sell rates are mandatory for each custom
        // code that the seeded list does not already cover.
        $unseeded = collect(app(SetupService::class)->customCurrencyRows(
            (array) session('setup.currencies', [])
        ))->pluck('code')->diff(Currency::pluck('code'));

        foreach ($unseeded as $code) {
            $rules["custom_rates.{$code}.buy"] = 'required|numeric|min:0.0001';
            $rules["custom_rates.{$code}.sell"] = 'required|numeric|min:0.0001';
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
