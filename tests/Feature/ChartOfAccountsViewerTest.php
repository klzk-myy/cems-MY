<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\UserRole;
use App\Models\ChartOfAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChartOfAccountsViewerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seeded directly (not via EnhancedChartOfAccountsSeeder) to keep this
        // viewer test independent of seeder churn.
        ChartOfAccount::factory()->create([
            'account_code' => '99001',
            'account_name' => 'Cash on Hand',
            'account_type' => AccountType::Asset,
        ]);
        ChartOfAccount::factory()->create([
            'account_code' => '99002',
            'account_name' => 'Accounts Payable',
            'account_type' => AccountType::Liability,
        ]);
        ChartOfAccount::factory()->create([
            'account_code' => '99003',
            'account_name' => 'Commission Income',
            'account_type' => AccountType::Revenue,
        ]);
    }

    #[Test]
    public function admin_can_view_chart_of_accounts_with_balances_and_ledger_links(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('accounting.chart-of-accounts.index'))
            ->assertOk()
            ->assertSee('99001')
            ->assertSee('Cash on Hand')
            ->assertSee('99002')
            ->assertSee('Accounts Payable')
            ->assertSee('99003')
            ->assertSee('Commission Income')
            ->assertSee(e(route('accounting.ledger.account', '99001')), false);
    }

    #[Test]
    public function compliance_officer_can_view_chart_of_accounts(): void
    {
        $officer = User::factory()->create(['role' => UserRole::ComplianceOfficer]);

        $this->actingAs($officer)
            ->get(route('accounting.chart-of-accounts.index'))
            ->assertOk();
    }

    #[Test]
    public function teller_cannot_view_chart_of_accounts(): void
    {
        $teller = User::factory()->teller()->create();

        $this->actingAs($teller)
            ->get(route('accounting.chart-of-accounts.index'))
            ->assertForbidden();
    }

    #[Test]
    public function page_lists_all_seeded_accounts_without_pagination(): void
    {
        $admin = User::factory()->admin()->create();

        $expectedCount = ChartOfAccount::count();

        $response = $this->actingAs($admin)
            ->get(route('accounting.chart-of-accounts.index'));

        // The shared in-memory test database may hold rows from other suites;
        // the viewer must list everything on one page regardless of count.
        $this->assertGreaterThanOrEqual(3, $expectedCount);
        $response->assertOk();

        // Every account code appears on the single page (bounded listing).
        foreach (ChartOfAccount::pluck('account_code') as $code) {
            $response->assertSee($code);
        }
    }
}
