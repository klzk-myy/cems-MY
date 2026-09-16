<?php

namespace App\Http\Requests\Accounting;

use App\Http\Requests\AuthorizedFormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class StoreJournalEntryRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'entry_date' => 'required|date',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'description' => 'required|string|max:500',
            'lines' => 'required|array|min:2',
            'lines.*.account_code' => [
                'required',
                'string',
                Rule::exists('chart_of_accounts', 'account_code')
                    ->where('allow_journal', true)
                    ->where('is_active', true),
            ],
            'lines.*.debit' => 'required|numeric|min:0',
            'lines.*.credit' => 'required|numeric|min:0',
            'lines.*.description' => 'nullable|string|max:255',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ((array) $this->input('lines', []) as $index => $line) {
                $debit = (float) ($line['debit'] ?? 0);
                $credit = (float) ($line['credit'] ?? 0);

                if ($debit > 0 && $credit > 0) {
                    $validator->errors()->add(
                        "lines.{$index}.debit",
                        'A journal line may carry a debit or a credit, not both.'
                    );
                } elseif ($debit == 0.0 && $credit == 0.0) {
                    $validator->errors()->add(
                        "lines.{$index}.debit",
                        'A journal line must carry a debit or a credit amount.'
                    );
                }
            }
        });
    }
}
