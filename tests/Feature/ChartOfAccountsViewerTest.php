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

        // Codes 9900x sort last; the index paginates by account_code at 50
        // per page, so the fixtures live on the final page.
        $lastPage = (int) ceil(ChartOfAccount::count() / 50);

        $this->actingAs($admin)
            ->get(route('accounting.chart-of-accounts.index', ['page' => $lastPage]))
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
    public function page_lists_all_seeded_accounts_across_pages(): void
    {
        $admin = User::factory()->admin()->create();

        $expectedCount = ChartOfAccount::count();

        // The shared in-memory test database may hold rows from other suites;
        // the viewer paginates, so collect codes across every page.
        $this->assertGreaterThanOrEqual(3, $expectedCount);

        $seen = collect();
        for ($page = 1; $page <= (int) ceil($expectedCount / 50); $page++) {
            $response = $this->actingAs($admin)
                ->get(route('accounting.chart-of-accounts.index', ['page' => $page]))
                ->assertOk();

            foreach (ChartOfAccount::pluck('account_code') as $code) {
                if (str_contains($response->getContent(), (string) $code)) {
                    $seen->push($code);
                }
            }
        }

        $this->assertEqualsCanonicalizing(
            ChartOfAccount::pluck('account_code')->all(),
            $seen->unique()->all()
        );
    }
}
