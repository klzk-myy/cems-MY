<?php

namespace App\Http\Requests;

use App\Enums\CddLevel;
use Illuminate\Foundation\Http\FormRequest;

class TransactionWizardStep2Request extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role->canCreateTransactions();
    }

    public function rules(): array
    {
        $cddLevel = $this->input('cdd_level');
        $rules = [
            'wizard_session_id' => ['required', 'string'],
            'cdd_level' => ['required', 'string', 'in:'.implode(',', array_column(CddLevel::cases(), 'value'))],
        ];

        // Base required fields
        $rules['customer.occupation'] = ['required', 'string', 'max:255'];
        $rules['customer.employer_name'] = ['nullable', 'string', 'max:255'];
        $rules['customer.employer_address'] = ['nullable', 'string', 'max:1000'];
        $rules['customer.annual_volume_estimate'] = ['nullable', 'numeric', 'min:0'];

        // CDD Level specific requirements
        if ($cddLevel === CddLevel::Standard->value || $cddLevel === CddLevel::Enhanced->value) {
            $rules['customer.proof_of_address'] = ['required', 'file', 'mimes:pdf,jpg,png', 'max:5120'];
        }

        if ($cddLevel === CddLevel::Enhanced->value) {
            $rules['customer.passport'] = ['required', 'file', 'mimes:pdf,jpg,png', 'max:5120'];
            $rules['customer.beneficial_owner'] = ['required', 'string', 'max:255'];
            $rules['customer.source_of_wealth'] = ['required', 'string', 'max:500'];
            $rules['transaction.expected_frequency'] = ['required', 'string', 'in:weekly,monthly,quarterly,annually'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'customer.proof_of_address.required' => 'Proof of address is required for Standard/Enhanced CDD',
            'customer.passport.required' => 'Passport is required for Enhanced CDD',
            'customer.beneficial_owner.required' => 'Beneficial ownership information is required',
        ];
    }
}
