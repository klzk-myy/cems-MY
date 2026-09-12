<?php

namespace App\Http\Requests;

class OpenCounterRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $floats = $this->input('opening_floats', []);

        if (is_array($floats) && $floats !== [] && ! array_is_list($floats)) {
            $this->merge([
                'opening_floats' => array_map(
                    static fn (string|int $code, mixed $amount): array => ['currency_id' => (string) $code, 'amount' => $amount],
                    array_keys($floats),
                    $floats,
                ),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'opening_floats' => 'required|array',
            'opening_floats.*.currency_id' => 'required|exists:currencies,code',
            'opening_floats.*.amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
