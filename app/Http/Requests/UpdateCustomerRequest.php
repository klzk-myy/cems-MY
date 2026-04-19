<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Update Customer Request
 *
 * Validates customer update data.
 * Applies to all authenticated tellers, managers, compliance officers, and admins.
 */
class UpdateCustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * All authenticated users with appropriate roles can update customers.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        $role = UserRole::tryFrom($user->role ?? '');

        return $role !== null && in_array($role, [
            UserRole::Teller,
            UserRole::Manager,
            UserRole::ComplianceOfficer,
            UserRole::Admin,
        ], true);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $customerId = $this->route('customer');

        return [
            'full_name' => [
                'sometimes',
                'required',
                'string',
                'min:2',
                'max:255',
            ],
            'id_type' => [
                'sometimes',
                'required',
                'string',
                'in:MyKad,Passport,Others',
            ],
            'id_number' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('customers', 'id_number_encrypted')
                    ->ignore($customerId)
                    ->where(function ($query) {
                        return $query->whereNotNull('id_number_encrypted');
                    }),
            ],
            'nationality' => [
                'sometimes',
                'required',
                'string',
                'size:2',
            ],
            'date_of_birth' => [
                'sometimes',
                'required',
                'date',
                'before:today',
                'after:1900-01-01',
            ],
            'phone' => [
                'sometimes',
                'required',
                'string',
                'regex:/^[0-9]{10,15}$/',
            ],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('customers', 'email')->ignore($customerId),
            ],
            'address' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'occupation' => [
                'nullable',
                'string',
                'max:255',
            ],
            'employer_name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'pep_status' => [
                'boolean',
            ],
            'is_active' => [
                'boolean',
            ],
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * Trims strings, sanitizes inputs, and formats MyKad numbers.
     */
    protected function prepareForValidation(): void
    {
        $data = [];

        if ($this->has('full_name')) {
            $data['full_name'] = trim($this->input('full_name'));
        }

        if ($this->has('id_number')) {
            $idNumber = trim($this->input('id_number'));
            // Remove spaces and hyphens from MyKad numbers
            if ($this->input('id_type') === 'MyKad') {
                $idNumber = preg_replace('/[\s\-]/', '', $idNumber);
            }
            $data['id_number'] = $idNumber;
        }

        if ($this->has('nationality')) {
            $data['nationality'] = strtoupper(trim($this->input('nationality')));
        }

        if ($this->has('phone')) {
            // Remove non-numeric characters except leading +
            $phone = $this->input('phone');
            if (str_starts_with($phone, '+')) {
                $data['phone'] = '+'.preg_replace('/[^0-9]/', '', substr($phone, 1));
            } else {
                $data['phone'] = preg_replace('/[^0-9]/', '', $phone);
            }
        }

        if ($this->has('email')) {
            $data['email'] = strtolower(trim($this->input('email')));
        }

        if ($this->has('address')) {
            $data['address'] = trim($this->input('address'));
        }

        if ($this->has('occupation')) {
            $data['occupation'] = trim($this->input('occupation'));
        }

        if ($this->has('employer_name')) {
            $data['employer_name'] = trim($this->input('employer_name'));
        }

        if ($this->has('id_type')) {
            $data['id_type'] = ucfirst(strtolower(trim($this->input('id_type'))));
        }

        $this->merge($data);
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'full_name.required' => 'Please enter the customer\'s full name.',
            'full_name.min' => 'The full name must be at least 2 characters.',
            'full_name.max' => 'The full name cannot exceed 255 characters.',
            'id_type.required' => 'Please select the ID type.',
            'id_type.in' => 'The ID type must be MyKad, Passport, or Others.',
            'id_number.required' => 'Please enter the ID number.',
            'id_number.unique' => 'A customer with this ID number already exists.',
            'id_number.max' => 'The ID number cannot exceed 50 characters.',
            'nationality.required' => 'Please enter the nationality.',
            'nationality.size' => 'The nationality must be a 2-letter country code (e.g., MY, SG).',
            'date_of_birth.required' => 'Please enter the date of birth.',
            'date_of_birth.date' => 'Please enter a valid date of birth.',
            'date_of_birth.before' => 'The date of birth must be before today.',
            'date_of_birth.after' => 'The date of birth must be after 1900.',
            'phone.required' => 'Please enter the phone number.',
            'phone.regex' => 'The phone number must be 10-15 digits.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email address is already registered.',
            'address.max' => 'The address cannot exceed 1000 characters.',
            'occupation.max' => 'The occupation cannot exceed 255 characters.',
            'employer_name.max' => 'The employer name cannot exceed 255 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'full_name' => 'full name',
            'id_type' => 'ID type',
            'id_number' => 'ID number',
            'nationality' => 'nationality',
            'date_of_birth' => 'date of birth',
            'phone' => 'phone number',
            'email' => 'email address',
            'address' => 'address',
            'occupation' => 'occupation',
            'employer_name' => 'employer name',
            'pep_status' => 'PEP status',
            'is_active' => 'active status',
        ];
    }
}
