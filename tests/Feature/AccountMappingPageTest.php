<?php

namespace Tests\Feature;

use App\Enums\AccountMappingKey;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\AccountingPeriod;
use App\Models\AccountMapping;
use App\Models\Branch;
use App\Models\SystemLog;
use App\Models\User;
use App\Services\Accounting\ExpenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
                'status' => 'Open',
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
}
