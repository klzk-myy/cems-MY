<?php

namespace App\Http\Requests;

use App\Enums\TellerAllocationStatus;
use App\Models\TellerAllocation;
use App\Models\User;
use Illuminate\Validation\Validator;

class HandoverCounterRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $counts = $this->input('physical_counts', []);

        if (is_array($counts) && $counts !== [] && ! array_is_list($counts)) {
            $this->merge([
                'physical_counts' => array_map(
                    static fn (string|int $code, mixed $amount): array => ['currency_id' => (string) $code, 'amount' => $amount],
                    array_keys($counts),
                    $counts,
                ),
            ]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $toUser = User::find($this->input('to_user_id'));

            if (! $toUser || $toUser->isTeller()) {
                return;
            }

            // The handover moves the outgoing operator's active allocations,
            // and TellerAllocationService::transferToTeller only accepts a
            // teller — surface that as a field error instead of a 500-flash.
            $hasActiveAllocations = TellerAllocation::query()
                ->where('user_id', $this->input('from_user_id'))
                ->where('status', TellerAllocationStatus::ACTIVE->value)
                ->whereDate('session_date', now()->toDateString())
                ->exists();

            if ($hasActiveAllocations) {
                $validator->errors()->add(
                    'to_user_id',
                    'The receiving operator must be a teller because the current operator has active stock allocations.'
                );
            }
        });
    }

    public function rules(): array
    {
        return [
            'from_user_id' => 'required|exists:users,id',
            'to_user_id' => 'required|exists:users,id',
            'supervisor_id' => 'required|exists:users,id',
            'physical_counts' => 'required|array',
            'physical_counts.*.currency_id' => 'required|exists:currencies,code',
            'physical_counts.*.amount' => 'required|numeric|min:0',
            'variance_notes' => 'nullable|string',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
