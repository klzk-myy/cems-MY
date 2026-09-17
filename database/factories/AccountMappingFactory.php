<?php

namespace Database\Factories;

use App\Enums\AccountMappingKey;
use App\Models\AccountMapping;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountMapping>
 */
class AccountMappingFactory extends Factory
{
    protected $model = AccountMapping::class;

    public function definition(): array
    {
        $key = fake()->unique()->randomElement(AccountMappingKey::cases());

        return [
            'key' => $key->value,
            'account_code' => $key->defaultAccount()->value,
            'description' => $key->usedBy(),
            'updated_by' => null,
        ];
    }
}
