<?php

namespace App\Http\Requests\Accounting;

use App\Enums\AccountMappingKey;
use App\Http\Requests\AuthorizedFormRequest;
use App\Services\Accounting\AccountMappingService;
use Illuminate\Validation\Validator;

/**
 * Validates account-mapping edits. Rows arrive as key/account_code pairs
 * (mappings[i][key], mappings[i][account_code]) because mapping keys contain
 * dots, which would corrupt old-input and error-bag lookup if used as field
 * names. Keys must be enum keys or dynamic per-currency keys
 * (cash.{CCY} / inventory.{CCY}); codes are checked against the key's
 * expected account type via AccountMappingService so page and runtime share
 * one rule.
 */
class UpdateAccountMappingsRequest extends AuthorizedFormRequest
{
    /**
     * Authorization is enforced by the route middleware (role:manage_account_mappings)
     * and the controller's requirePermission check.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mappings' => ['required', 'array'],
            'mappings.*.key' => ['required', 'string', 'max:50'],
            'mappings.*.account_code' => ['nullable', 'string', 'exists:chart_of_accounts,account_code'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mappings.required' => 'No mappings were submitted.',
            'mappings.*.account_code.exists' => 'The selected account does not exist in the chart of accounts.',
            'reason.required' => 'A change reason is required — it is recorded on the audit trail.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var AccountMappingService $service */
            $service = $this->container->make(AccountMappingService::class);

            foreach ($this->input('mappings', []) as $index => $row) {
                $key = (string) ($row['key'] ?? '');
                $accountCode = $row['account_code'] ?? null;

                if (! $this->isKnownKey($key)) {
                    $validator->errors()->add("mappings.{$index}.key", "Unknown mapping key '{$key}'.");

                    continue;
                }

                if ($accountCode === null || $accountCode === '') {
                    if (AccountMappingKey::tryFrom($key) !== null) {
                        $validator->errors()->add(
                            "mappings.{$index}.account_code",
                            'This mapping is required — a posting path must always resolve to an account.'
                        );
                    }

                    continue;
                }

                try {
                    $service->validateAccount($key, $accountCode);
                } catch (\Throwable $e) {
                    $validator->errors()->add("mappings.{$index}.account_code", $e->getMessage());
                }
            }
        });
    }

    private function isKnownKey(string $key): bool
    {
        return AccountMappingKey::tryFrom($key) !== null
            || preg_match('/^(cash|inventory)\.[A-Z]{3}$/', $key) === 1;
    }

    /**
     * Flatten the submitted rows to a key => account_code map.
     *
     * @return array<string, string|null>
     */
    public function validatedMappings(): array
    {
        /** @var array<int, array{key: string, account_code: ?string}> $rows */
        $rows = $this->validated('mappings') ?? [];

        return collect($rows)
            ->mapWithKeys(fn (array $row) => [$row['key'] => $row['account_code'] ?? null])
            ->all();
    }
}
