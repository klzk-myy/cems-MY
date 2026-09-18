<?php

namespace Tests\Feature;

use App\Enums\AccountMappingKey;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\AccountingPeriod;
use App\Models\AccountMapping;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\SystemLog;
use App\Models\User;
use App\Services\Accounting\ExpenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature coverage for the account-mappings management page: the
 * manage_account_mappings permission gate, audited updates through the
 * key/account_code row payload, validation, and a runtime regression
 * proving posting services consume the table.
 */
class AccountMappingPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    /**
     * Build the key/account_code row payload for one mapping.
     *
     * @return array<string, mixed>
     */
    private function payload(string $key, ?string $accountCode, string $reason = 'test change'): array
    {
        return [
            'reason' => $reason,
            'mappings' => [
                ['key' => $key, 'account_code' => $accountCode],
            ],
        ];
    }

    #[Test]
    public function admin_and_accountant_can_view_mappings_page(): void
    {
        $this->actingAs($this->admin);
        $this->setMfaVerification($this->admin);
        $this->get(route('accounting.mappings.index'))
            ->assertOk()
            ->assertSee('cash.myr')
            ->assertSee('inventory.default')
            ->assertSee('close.retained_earnings');

        $accountant = User::factory()->create(['role' => UserRole::Accountant->value]);
        $this->actingAs($accountant);
        $this->setMfaVerification($accountant);
        $this->get(route('accounting.mappings.index'))->assertOk();
    }

    #[Test]
    public function teller_and_manager_are_forbidden(): void
    {
        $teller = User::factory()->teller()->create();
        $this->actingAs($teller);
        $this->setMfaVerification($teller);
        $this->get(route('accounting.mappings.index'))->assertForbidden();

        // Manager holds access_accounting but not manage_account_mappings —
        // the dedicated permission is what gates this page.
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager);
        $this->setMfaVerification($manager);
        $this->get(route('accounting.mappings.index'))->assertForbidden();
    }

    #[Test]
    public function guest_is_redirected_to_login(): void
    {
        $this->get(route('accounting.mappings.index'))->assertRedirect('/login');
    }

    #[Test]
    public function update_requires_password_confirmation(): void
    {
        $this->actingAs($this->admin);
        $this->setMfaVerification($this->admin);

        $this->post(route('accounting.mappings.update'), $this->payload('cash.myr', '1100'))
            ->assertRedirect(route('password.confirm'));
    }

    #[Test]
    public function update_persists_override_and_audits(): void
    {
        $this->actingAs($this->admin);
        $this->setMfaVerification($this->admin);

        $this->withSession($this->passwordConfirmedSession())
            ->post(route('accounting.mappings.update'), $this->payload('cash.myr', '1100', 'BNM directive'))
            ->assertRedirect(route('accounting.mappings.index'));

        $this->assertSame('1100', AccountMapping::where('key', 'cash.myr')->value('account_code'));

        $log = SystemLog::where('action', 'account_mapping_updated')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('BNM directive', $log->new_values['reason']);
        $this->assertSame($this->admin->id, $log->user_id);
    }

    #[Test]
    public function update_requires_reason(): void
    {
        $this->actingAs($this->admin);
        $this->setMfaVerification($this->admin);

        $this->withSession($this->passwordConfirmedSession())
            ->post(route('accounting.mappings.update'), [
                'mappings' => [['key' => 'cash.myr', 'account_code' => '1100']],
            ])
            ->assertSessionHasErrors('reason');
    }

    #[Test]
    public function update_rejects_nonexistent_account(): void
    {
        $this->actingAs($this->admin);
        $this->setMfaVerification($this->admin);

        $this->withSession($this->passwordConfirmedSession())
            ->post(route('accounting.mappings.update'), $this->payload('cash.myr', '99999'))
            ->assertSessionHasErrors('mappings.0.account_code');
    }

    #[Test]
    public function update_rejects_wrong_account_type(): void
    {
        $this->actingAs($this->admin);
        $this->setMfaVerification($this->admin);

        // 5000 is a Revenue account; cash.myr requires Asset.
        $this->withSession($this->passwordConfirmedSession())
            ->post(route('accounting.mappings.update'), $this->payload('cash.myr', '5000'))
            ->assertSessionHasErrors('mappings.0.account_code');
    }

    #[Test]
    public function update_rejects_empty_value_on_fixed_key(): void
    {
        $this->actingAs($this->admin);
        $this->setMfaVerification($this->admin);

        $this->withSession($this->passwordConfirmedSession())
            ->post(route('accounting.mappings.update'), $this->payload('cash.myr', ''))
            ->assertSessionHasErrors('mappings.0.account_code');
    }

    #[Test]
    public function update_rejects_unknown_key(): void
    {
        $this->actingAs($this->admin);
        $this->setMfaVerification($this->admin);

        $this->withSession($this->passwordConfirmedSession())
            ->post(route('accounting.mappings.update'), $this->payload('bogus.key', '1000'))
            ->assertSessionHasErrors('mappings.0.key');
    }

    #[Test]
    public function update_removes_dynamic_currency_override_when_blank(): void
    {
        $this->actingAs($this->admin);
        $this->setMfaVerification($this->admin);

        $this->withSession($this->passwordConfirmedSession())
            ->post(route('accounting.mappings.update'), $this->payload('inventory.USD', '2001'))
            ->assertRedirect(route('accounting.mappings.index'));

        $this->assertSame('2001', AccountMapping::where('key', 'inventory.USD')->value('account_code'));

        $this->withSession($this->passwordConfirmedSession())
            ->post(route('accounting.mappings.update'), $this->payload('inventory.USD', ''))
            ->assertRedirect(route('accounting.mappings.index'));

        $this->assertNull(AccountMapping::where('key', 'inventory.USD')->first());
    }

    #[Test]
    public function remapped_account_flows_into_posted_journal_lines(): void
    {
        // Regression: posting services must resolve through account_mappings
        // at runtime, not hardcoded codes.
        AccountMapping::where('key', AccountMappingKey::CashMyr->value)
            ->update(['account_code' => '1100']);

        AccountingPeriod::firstOrCreate(
            ['period_code' => now()->format('Y-m')],
            [
                'start_date' => now()->startOfMonth()->toDateString(),
                'end_date' => now()->endOfMonth()->toDateString(),
                'period_type' => 'month',
                'status' => 'open',
            ]
        );

        $branch = Branch::factory()->create();
        $journal = app(ExpenseService::class)->fundPettyCash($branch, $this->admin, '500.00');

        $creditLine = $journal->lines->first(fn ($line) => (float) $line->credit > 0);
        $debitLine = $journal->lines->first(fn ($line) => (float) $line->debit > 0);
        $this->assertSame('1100', $creditLine->account_code);
        $this->assertSame('1050', $debitLine->account_code);
        $this->assertSame($branch->id, $journal->branch_id);
    }

    #[Test]
    public function admin_can_provision_dedicated_accounts_for_a_currency(): void
    {
        $this->actingAs($this->admin);
        $this->setMfaVerification($this->admin);

        $this->assertNull(AccountMapping::where('key', 'inventory.USD')->first());

        $this->withSession($this->passwordConfirmedSession())
            ->post(route('accounting.mappings.provision', 'USD'))
            ->assertRedirect(route('accounting.mappings.index'))
            ->assertSessionHas('success');

        // USD has dedicated enum accounts — provisioning maps to them.
        $this->assertSame('1001', AccountMapping::where('key', 'cash.USD')->value('account_code'));
        $this->assertSame('2001', AccountMapping::where('key', 'inventory.USD')->value('account_code'));

        $this->assertDatabaseHas('system_logs', [
            'action' => 'currency_accounts_provisioned',
            'entity_type' => 'Currency',
        ]);
    }

    #[Test]
    public function provision_requires_the_manage_account_mappings_permission(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager);
        $this->setMfaVerification($manager);

        $this->withSession($this->passwordConfirmedSession())
            ->post(route('accounting.mappings.provision', 'USD'))
            ->assertForbidden();
    }

    #[Test]
    public function provision_requires_password_confirmation(): void
    {
        $this->actingAs($this->admin);
        $this->setMfaVerification($this->admin);

        $this->post(route('accounting.mappings.provision', 'USD'))
            ->assertRedirect(route('password.confirm'));

        $this->assertNull(AccountMapping::where('key', 'cash.USD')->first());
    }

    #[Test]
    public function provision_rejects_a_disabled_currency(): void
    {
        Currency::factory()->create(['code' => 'CHF', 'is_active' => false]);

        $this->actingAs($this->admin);
        $this->setMfaVerification($this->admin);

        $this->withSession($this->passwordConfirmedSession())
            ->post(route('accounting.mappings.provision', 'CHF'))
            ->assertRedirect(route('accounting.mappings.index'))
            ->assertSessionHas('error');

        $this->assertNull(AccountMapping::where('key', 'cash.CHF')->first());
    }

    #[Test]
    public function partially_provisioned_currency_is_listed_and_completed(): void
    {
        // A currency with only one leg mapped (e.g. a manual cash override)
        // must still be offered for provisioning so the pair completes.
        Currency::factory()->create(['code' => 'CHF']);
        AccountMapping::create([
            'key' => 'cash.CHF',
            'account_code' => '1001',
            'description' => 'manual override',
        ]);

        $this->actingAs($this->admin);
        $this->setMfaVerification($this->admin);

        $this->get(route('accounting.mappings.index'))
            ->assertOk()
            ->assertSee('Provision CHF');

        $this->withSession($this->passwordConfirmedSession())
            ->post(route('accounting.mappings.provision', 'CHF'))
            ->assertRedirect(route('accounting.mappings.index'))
            ->assertSessionHas('success');

        // The manual row is preserved; only the missing leg is added.
        $this->assertSame('1001', AccountMapping::where('key', 'cash.CHF')->value('account_code'));
        $this->assertNotNull(AccountMapping::where('key', 'inventory.CHF')->first());
    }

    #[Test]
    public function update_rejects_dynamic_keys_for_unknown_currencies(): void
    {
        $this->actingAs($this->admin);
        $this->setMfaVerification($this->admin);

        // ZZZ matches the key shape but no such currency exists — the row
        // must not be planted.
        $this->withSession($this->passwordConfirmedSession())
            ->post(route('accounting.mappings.update'), $this->payload('cash.ZZZ', '1001'))
            ->assertSessionHasErrors('mappings.0.key');

        $this->assertNull(AccountMapping::where('key', 'cash.ZZZ')->first());
    }

    #[Test]
    public function index_renders_read_only_defaults_when_the_mappings_table_is_missing(): void
    {
        // Databases that have not run accounting:install-mappings yet must
        // still render the page — posting paths resolve enum defaults, so
        // the page shows them read-only instead of throwing a QueryException.
        // SQLite DDL is transactional: the drop rolls back with the test.
        Schema::drop('account_mappings');

        $this->actingAs($this->admin);
        $this->setMfaVerification($this->admin);

        $this->get(route('accounting.mappings.index'))
            ->assertOk()
            ->assertSee('accounting:install-mappings')
            ->assertSee('cash.myr')
            ->assertDontSee('Save Mappings');
    }
}
