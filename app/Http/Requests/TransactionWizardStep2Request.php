<?php

namespace App\Http\Requests;

use App\Enums\CddLevel;
use Illuminate\Validation\Validator;

class TransactionWizardStep2Request extends AuthorizedFormRequest
{
    /** File keys accepted under customer.* - anything else is rejected. */
    private const ALLOWED_DOCUMENT_FIELDS = ['proof_of_address', 'passport'];

    public function authorize(): bool
    {
        return $this->user()->role->canCreateTransaction();
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
        $rules['customer.annual_volume_myr'] = ['nullable', 'numeric', 'min:0'];

        // KYC uploads are always validated (type/size) whenever present, even
        // when optional for the current CDD level; presence is only required
        // for Standard/Enhanced flows.
        $rules['customer.proof_of_address'] = ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'];
        $rules['customer.passport'] = ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'];

        // CDD Level specific requirements
        if ($cddLevel === CddLevel::Standard->value || $cddLevel === CddLevel::Enhanced->value) {
            $rules['customer.proof_of_address'] = ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'];
        }

        if ($cddLevel === CddLevel::Enhanced->value) {
            $rules['customer.passport'] = ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'];
            $rules['customer.beneficial_owner'] = ['required', 'string', 'max:255'];
            $rules['customer.source_of_wealth'] = ['required', 'string', 'max:500'];
            $rules['transaction.expected_frequency'] = ['required', 'string', 'in:weekly,monthly,quarterly,annually'];
        }

        return $rules;
    }

    /**
     * Reject unexpected file uploads under customer.* so unvalidated files can
     * never reach storage via processDocuments or any other consumer.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $files = $this->file('customer');

            if (! is_array($files)) {
                return;
            }

            foreach (array_keys($files) as $key) {
                if (! in_array($key, self::ALLOWED_DOCUMENT_FIELDS, true)) {
                    $validator->errors()->add(
                        "customer.$key",
                        "Unexpected file upload: '$key' is not an accepted document."
                    );
                }
            }
        });
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
