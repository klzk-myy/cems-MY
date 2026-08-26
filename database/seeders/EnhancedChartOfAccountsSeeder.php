<?php

namespace Database\Seeders;

use App\Enums\AccountCode;
use App\Models\ChartOfAccount;
use Illuminate\Database\Seeder;

/**
 * Seeds the chart of accounts from the AccountCode enum.
 *
 * The enum is the single source of truth: every case becomes a
 * chart_of_accounts row keyed by its code, so reruns are idempotent and
 * no duplicate or orphaned codes can be introduced.
 */
class EnhancedChartOfAccountsSeeder extends Seeder
{
    /**
     * Account codes that consumers rely on but that are not (yet) enum cases.
     *
     * @var array<int, array{code: string, name: string, type: string, class: string}>
     */
    protected array $extraAccounts = [
        ['code' => '1011', 'name' => 'Cash - Foreign Currencies', 'type' => 'Asset', 'class' => 'Cash'],
    ];

    public function run(): void
    {
        foreach (AccountCode::cases() as $account) {
            ChartOfAccount::updateOrCreate(
                ['account_code' => $account->value],
                [
                    'account_name' => $account->description(),
                    'account_type' => $account->category(),
                    'is_active' => true,
                ]
            );
        }

        foreach ($this->extraAccounts as $extra) {
            ChartOfAccount::firstOrCreate(
                ['account_code' => $extra['code']],
                [
                    'account_name' => $extra['name'],
                    'account_type' => $extra['type'],
                    'account_class' => $extra['class'],
                    'is_active' => true,
                ]
            );
        }
    }
}
