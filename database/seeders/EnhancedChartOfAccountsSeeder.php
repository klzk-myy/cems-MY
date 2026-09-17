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

    /**
     * Account class assignments used by CashFlowService activity grouping.
     * Only classes the cash-flow report reads are assigned; everything else
     * stays unclassified.
     *
     * @var array<string, string>
     */
    /** @var array<int|string, string> */
    protected array $classByCode = [
        // Cash & equivalents
        '1000' => 'Cash', '1001' => 'Cash', '1002' => 'Cash', '1003' => 'Cash',
        '1004' => 'Cash', '1005' => 'Cash', '1006' => 'Cash', '1007' => 'Cash',
        '1100' => 'Cash', '1101' => 'Cash', '1102' => 'Cash', '1103' => 'Cash',
        '1200' => 'Cash', '1201' => 'Cash', '1202' => 'Cash',
        // Inventory
        '2000' => 'Inventory', '2001' => 'Inventory', '2002' => 'Inventory',
        '2003' => 'Inventory', '2004' => 'Inventory', '2005' => 'Inventory',
        '2006' => 'Inventory', '2007' => 'Inventory',
        // Receivables / payables
        '2100' => 'Receivable', '2300' => 'Receivable',
        '3000' => 'Payable', '3100' => 'Payable',
        // Contributed equity (capital issuance only — retained earnings are
        // deliberately unclassified so period-close transfers are not
        // misreported as financing inflows)
        '4000' => 'Capital', '4001' => 'Capital', '4002' => 'Capital',
    ];

    public function run(): void
    {
        foreach (AccountCode::cases() as $account) {
            ChartOfAccount::updateOrCreate(
                ['account_code' => $account->value],
                [
                    'account_name' => $account->description(),
                    'account_type' => $account->category(),
                    'account_class' => $this->classByCode[$account->value] ?? null,
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
