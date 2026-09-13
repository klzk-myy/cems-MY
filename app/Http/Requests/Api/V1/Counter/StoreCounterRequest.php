<?php

namespace App\Http\Requests\Api\V1\Counter;

use App\Http\Requests\ApiFormRequest;
use App\Models\Branch;
use App\Models\Counter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class StoreCounterRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user || ! $user->can('create', Counter::class)) {
            return false;
        }

        // Managers are branch-scoped: the target branch must be their own.
        // CounterPolicy::create cannot check it because no Counter instance
        // exists yet. A missing branch_id falls through to validation so it
        // reports 'required' instead of masking as a 403.
        $branchId = $this->input('branch_id');

        return $user->isAdmin() || $branchId === null || (int) $branchId === $user->branch_id;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => 'required|string|max:10|unique:counters,code',
            'name' => 'required|string|max:255',
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where(
                    fn ($query) => $query
                        ->whereIn('type', [Branch::TYPE_BRANCH, Branch::TYPE_SUB_BRANCH])
                        ->where('is_active', true)
                ),
            ],
            'status' => 'nullable|in:active,inactive,maintenance',
        ];
    }
}
