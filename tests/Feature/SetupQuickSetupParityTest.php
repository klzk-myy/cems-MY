<?php

namespace Tests\Feature;

use App\Enums\JournalEntryStatus;
use App\Models\AccountingPeriod;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Services\Accounting\AccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SetupQuickSetupParityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function validPayload(array $overrides = []): array
    {
        return array_merge([
            'business_name' => 'Test Money Changer',
            'admin_email' => 'admin@example.com',
            'admin_password' => 'Sup3rSecure!Pass',
            'base_currency' => 'MYR',
        ], $overrides);
    }

    #[Test]
    public function quick_setup_seeds_current_fiscal_year_and_open_monthly_periods(): void
    {
        $response = $this->postJson(route('setup.quick'), $this->validPayload());

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertTrue(
            FiscalYear::where('year_code', 'FY'.now()->year)
                ->where('status', 'Open')
                ->exists(),
            'Current fiscal year must exist and be open after quick setup'
        );

        foreach ([now(), now()->subMonth(), now()->addMonth()] as $month) {
            $period = AccountingPeriod::where('period_code', $month->format('Y-m'))->first();

            $this->assertNotNull($period, "Period {$month->format('Y-m')} must exist after quick setup");
            $this->assertTrue($period->isOpen(), "Period {$month->format('Y-m')} must be open after quick setup");
        }
    }

    #[Test]
    public function quick_setup_seeds_exchange_rates_by_default(): void
    {
        $response = $this->postJson(route('setup.quick'), $this->validPayload());

        $response->assertOk();

        $this->assertTrue(ExchangeRate::exists(), 'Exchange rates must be seeded by default');
    }

    #[Test]
    public function quick_setup_skips_exchange_rates_when_explicitly_opted_out_but_still_seeds_fiscal_preconditions(): void
    {
        $response = $this->postJson(route('setup.quick'), $this->validPayload([
            'setup_exchange_rates' => false,
        ]));

        $response->assertOk();

        $this->assertFalse(ExchangeRate::exists(), 'Exchange rates must not be seeded when opted out');
        $this->assertTrue(FiscalYear::where('status', 'Open')->exists());
        $this->assertTrue(
            AccountingPeriod::whereDate('start_date', '<=', now())
                ->whereDate('end_date', '>=', now())
                ->where('status', 'Open')
                ->exists()
        );
    }

    #[Test]
    public function transaction_posting_succeeds_end_to_end_after_quick_setup(): void
    {
        $this->postJson(route('setup.quick'), $this->validPayload())->assertOk();

        /** @var AccountingService $accounting */
        $accounting = app(AccountingService::class);

        $entry = $accounting->createJournalEntry(
            lines: [
                ['account_code' => '1000', 'debit' => '1000.00', 'credit' => '0', 'description' => 'Cash in'],
                ['account_code' => '4000', 'debit' => '0', 'credit' => '1000.00', 'description' => 'Owner equity'],
            ],
            referenceType: 'Manual',
            description: 'End-to-end posting after quick setup',
            entryDate: now()->toDateString(),
        );

        $this->assertSame(JournalEntryStatus::Posted, $entry->status);

        $expectedPeriodId = AccountingPeriod::forDate(now()->toDateString())->value('id');
        $this->assertSame($expectedPeriodId, $entry->period_id, 'Entry must be attached to the quick-setup period');
    }

    #[Test]
    public function wizard_completion_path_still_seeds_fiscal_year_and_period_and_flashes_sanctions_notice(): void
    {
        $this->withSession([
            'setup' => [
                'business' => ['business_name' => 'Wizard Co'],
                'admin' => [
                    'admin_name' => 'admin',
                    'admin_email' => 'wizard-admin@example.com',
                    'admin_password' => 'Sup3rSecure!Pass',
                ],
            ],
        ]);

        $response = $this->postJson(route('setup.complete'));

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertTrue(FiscalYear::where('status', 'Open')->exists());
        $this->assertTrue(AccountingPeriod::where('status', 'Open')->exists());
        $this->assertEquals(
            'Sanctions lists are not loaded yet. Run "php artisan sanctions:update" now - '
            .'sanctions screening is ineffective until the lists are imported.',
            session('info')
        );
    }

    #[Test]
    public function step3_folds_custom_currencies_into_the_active_selection(): void
    {
        $response = $this->post(route('setup.step3'), [
            'base_currency' => 'MYR',
            'active_currencies' => ['MYR', 'USD'],
            'custom_currencies' => [
                ['code' => 'thb', 'name' => 'Thai Baht', 'symbol' => '฿'],
                ['code' => ' vnd ', 'name' => 'Vietnamese Đồng', 'symbol' => '₫'],
            ],
        ]);

        $response->assertRedirect(route('setup.wizard', ['step' => 4]));

        $currencies = session('setup.currencies');
        $this->assertSame(
            [
                ['code' => 'THB', 'name' => 'Thai Baht', 'symbol' => '฿'],
                ['code' => 'VND', 'name' => 'Vietnamese Đồng', 'symbol' => '₫'],
            ],
            $currencies['custom_currencies'],
            'Custom rows must be uppercased/trimmed and stored for completion'
        );
        $this->assertEqualsCanonicalizing(
            ['MYR', 'USD', 'THB', 'VND'],
            $currencies['active_currencies'],
            'Custom codes must be merged into the active set'
        );
    }

    #[Test]
    public function step3_accepts_a_legacy_single_custom_currency_shape(): void
    {
        $response = $this->post(route('setup.step3'), [
            'base_currency' => 'MYR',
            'active_currencies' => ['MYR', 'USD'],
            'custom_currency_code' => 'thb',
            'custom_currency_name' => 'Thai Baht',
            'custom_currency_symbol' => '฿',
        ]);

        $response->assertRedirect(route('setup.wizard', ['step' => 4]));

        $currencies = session('setup.currencies');
        $this->assertSame('THB', $currencies['custom_currencies'][0]['code']);
        $this->assertContains('THB', $currencies['active_currencies']);
    }

    #[Test]
    public function step3_requires_a_name_when_a_custom_currency_code_is_given(): void
    {
        $response = $this->post(route('setup.step3'), [
            'base_currency' => 'MYR',
            'active_currencies' => ['MYR'],
            'custom_currencies' => [
                ['code' => 'THB'],
            ],
        ]);

        $response->assertSessionHasErrors('custom_currencies.0.name');
    }

    #[Test]
    public function step3_ignores_empty_custom_currency_rows(): void
    {
        $response = $this->post(route('setup.step3'), [
            'base_currency' => 'MYR',
            'active_currencies' => ['MYR'],
            'custom_currencies' => [
                ['code' => '', 'name' => '', 'symbol' => ''],
            ],
        ]);

        $response->assertRedirect(route('setup.wizard', ['step' => 4]));
        $this->assertSame([], session('setup.currencies.custom_currencies'));
        $this->assertSame(['MYR'], session('setup.currencies.active_currencies'));
    }

    #[Test]
    public function step4_requires_buy_and_sell_rates_for_each_unseeded_custom_currency(): void
    {
        $this->withSession([
            'setup.currencies' => [
                'base_currency' => 'MYR',
                'active_currencies' => ['MYR', 'THB', 'VND'],
                'custom_currencies' => [
                    ['code' => 'THB', 'name' => 'Thai Baht', 'symbol' => '฿'],
                    ['code' => 'VND', 'name' => 'Vietnamese Đồng', 'symbol' => '₫'],
                ],
            ],
        ]);

        $response = $this->post(route('setup.step4'), [
            'use_default_rates' => '1',
        ]);

        $response->assertSessionHasErrors([
            'custom_rates.THB.buy',
            'custom_rates.THB.sell',
            'custom_rates.VND.buy',
            'custom_rates.VND.sell',
        ]);
    }

    #[Test]
    public function step4_still_requires_rates_for_a_legacy_single_custom_currency(): void
    {
        $this->withSession([
            'setup.currencies' => [
                'base_currency' => 'MYR',
                'active_currencies' => ['MYR', 'THB'],
                'custom_currency_code' => 'THB',
                'custom_currency_name' => 'Thai Baht',
            ],
        ]);

        $response = $this->post(route('setup.step4'), [
            'use_default_rates' => '1',
        ]);

        $response->assertSessionHasErrors([
            'custom_rates.THB.buy',
            'custom_rates.THB.sell',
        ]);
    }

    #[Test]
    public function wizard_completion_creates_custom_currency_rate_stock_and_position(): void
    {
        $this->withSession([
            'setup' => [
                'business' => ['business_name' => 'Wizard Co'],
                'admin' => [
                    'admin_name' => 'admin',
                    'admin_email' => 'wizard-admin@example.com',
                    'admin_password' => 'Sup3rSecure!Pass',
                ],
                'currencies' => [
                    'base_currency' => 'MYR',
                    'active_currencies' => ['MYR', 'USD', 'THB'],
                    'custom_currencies' => [
                        ['code' => 'THB', 'name' => 'Thai Baht', 'symbol' => '฿'],
                    ],
                ],
                'rates' => [
                    'use_default_rates' => '1',
                    'custom_rates' => ['THB' => ['buy' => '0.1280', 'sell' => '0.1320']],
                ],
                'stock' => [
                    'initial_myr_cash' => '1000',
                    'initial_stock' => ['USD' => '500', 'THB' => '20000'],
                ],
                'opening_balance' => [
                    'opening_balance_myr' => '1000',
                    'opening_balance_foreign' => ['USD' => '500', 'THB' => '20000'],
                ],
            ],
        ]);

        $response = $this->postJson(route('setup.complete'));

        $response->assertOk()->assertJson(['success' => true]);

        $currency = Currency::where('code', 'THB')->first();
        $this->assertNotNull($currency, 'Custom currency must be created');
        $this->assertSame('Thai Baht', $currency->name);
        $this->assertTrue($currency->is_active);

        $rate = ExchangeRate::where('currency_code', 'THB')->first();
        $this->assertNotNull($rate, 'Custom currency must get an exchange rate');
        $this->assertSame('setup_custom', $rate->source);

        $this->assertDatabaseHas('branch_pools', ['currency_code' => 'THB', 'available_balance' => '20000.0000']);
        $this->assertDatabaseHas('currency_positions', ['currency_code' => 'THB', 'quantity' => '20000.0000']);
    }

    #[Test]
    public function wizard_completion_creates_multiple_custom_currencies(): void
    {
        $this->withSession([
            'setup' => [
                'business' => ['business_name' => 'Wizard Co'],
                'admin' => [
                    'admin_name' => 'admin',
                    'admin_email' => 'wizard-admin@example.com',
                    'admin_password' => 'Sup3rSecure!Pass',
                ],
                'currencies' => [
                    'base_currency' => 'MYR',
                    'active_currencies' => ['MYR', 'USD', 'THB', 'VND'],
                    'custom_currencies' => [
                        ['code' => 'THB', 'name' => 'Thai Baht', 'symbol' => '฿'],
                        ['code' => 'VND', 'name' => 'Vietnamese Đồng', 'symbol' => '₫'],
                    ],
                ],
                'rates' => [
                    'use_default_rates' => '1',
                    'custom_rates' => [
                        'THB' => ['buy' => '0.1280', 'sell' => '0.1320'],
                        'VND' => ['buy' => '0.00017', 'sell' => '0.00019'],
                    ],
                ],
                'stock' => [
                    'initial_myr_cash' => '1000',
                    'initial_stock' => ['USD' => '500', 'THB' => '20000', 'VND' => '1000000'],
                ],
                'opening_balance' => [
                    'opening_balance_myr' => '1000',
                    'opening_balance_foreign' => ['USD' => '500', 'THB' => '20000', 'VND' => '1000000'],
                ],
            ],
        ]);

        $response = $this->postJson(route('setup.complete'));

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertSame('Thai Baht', Currency::where('code', 'THB')->value('name'));
        $this->assertSame('Vietnamese Đồng', Currency::where('code', 'VND')->value('name'));
        $this->assertDatabaseHas('exchange_rates', ['currency_code' => 'THB', 'source' => 'setup_custom']);
        $this->assertDatabaseHas('exchange_rates', ['currency_code' => 'VND', 'source' => 'setup_custom']);
        $this->assertDatabaseHas('branch_pools', ['currency_code' => 'VND', 'available_balance' => '1000000.0000']);
    }

    #[Test]
    public function wizard_opening_balance_writes_journal_lines_and_ledger_rows(): void
    {
        $this->withSession([
            'setup' => [
                'business' => ['business_name' => 'Wizard Co'],
                'admin' => [
                    'admin_name' => 'admin',
                    'admin_email' => 'wizard-admin@example.com',
                    'admin_password' => 'Sup3rSecure!Pass',
                ],
                'currencies' => [
                    'base_currency' => 'MYR',
                    'active_currencies' => ['MYR', 'USD', 'THB'],
                    'custom_currency_code' => 'THB',
                    'custom_currency_name' => 'Thai Baht',
                    'custom_currency_symbol' => '฿',
                ],
                'rates' => [
                    'use_default_rates' => '1',
                    'custom_rates' => ['THB' => ['buy' => '0.1280', 'sell' => '0.1320']],
                ],
                'stock' => [
                    'initial_myr_cash' => '1000',
                    'initial_stock' => ['USD' => '500', 'THB' => '20000'],
                ],
                'opening_balance' => [
                    'opening_balance_myr' => '1000',
                    'opening_balance_foreign' => ['USD' => '500', 'THB' => '20000'],
                ],
            ],
        ]);

        $response = $this->postJson(route('setup.complete'));

        $response->assertOk()->assertJson(['success' => true]);

        $entry = JournalEntry::where('reference_type', 'Opening Balance')->first();
        $this->assertNotNull($entry, 'Opening balance journal entry must exist');

        // Every journal line must have a matching account_ledger row — reports
        // read the ledger, so a line without one is invisible to trial balance.
        foreach ($entry->lines as $line) {
            $this->assertDatabaseHas('account_ledger', [
                'journal_entry_id' => $entry->id,
                'account_code' => $line->account_code,
                'debit' => $line->debit,
                'credit' => $line->credit,
            ]);
        }

        $this->assertSame(3, $entry->lines->count());
        $this->assertDatabaseHas('account_ledger', ['journal_entry_id' => $entry->id, 'account_code' => '4000', 'credit' => '21500.0000']);
    }

    #[Test]
    public function wizard_completion_restores_a_soft_deleted_custom_currency(): void
    {
        // A soft-deleted row still owns its PK — replaying the code through
        // the wizard must restore it, not crash the whole setup transaction
        // on a duplicate-key insert.
        Currency::factory()->create(['code' => 'AAA', 'is_active' => false])->delete();

        $this->withSession([
            'setup' => [
                'business' => ['business_name' => 'Wizard Co'],
                'admin' => [
                    'admin_name' => 'admin',
                    'admin_email' => 'wizard-admin@example.com',
                    'admin_password' => 'Sup3rSecure!Pass',
                ],
                'currencies' => [
                    'base_currency' => 'MYR',
                    'active_currencies' => ['MYR', 'USD', 'AAA'],
                    'custom_currencies' => [
                        ['code' => 'AAA', 'name' => 'Restored Coin', 'symbol' => 'A'],
                    ],
                ],
                'rates' => [
                    'use_default_rates' => '1',
                    'custom_rates' => ['AAA' => ['buy' => '0.25', 'sell' => '0.26']],
                ],
                'stock' => [
                    'initial_myr_cash' => '1000',
                    'initial_stock' => ['USD' => '500', 'AAA' => '100'],
                ],
                'opening_balance' => [
                    'opening_balance_myr' => '1000',
                    'opening_balance_foreign' => ['USD' => '500', 'AAA' => '100'],
                ],
            ],
        ]);

        $response = $this->postJson(route('setup.complete'));

        $response->assertOk()->assertJson(['success' => true]);

        $currency = Currency::find('AAA');
        $this->assertNotNull($currency, 'Soft-deleted currency must be restored, not re-inserted');
        $this->assertTrue($currency->is_active);
    }

    #[Test]
    public function quick_setup_flashes_sanctions_bootstrap_notice(): void
    {
        $response = $this->postJson(route('setup.quick'), $this->validPayload());

        $response->assertOk();

        $this->assertEquals(
            'Sanctions lists are not loaded yet. Run "php artisan sanctions:update" now - '
            .'sanctions screening is ineffective until the lists are imported.',
            session('info')
        );
    }
}
