<?php

namespace Tests\Unit;

use App\Enums\AccountMappingKey;
use App\Exceptions\Domain\AccountingPeriodException;
use App\Models\AccountMapping;
use App\Models\SystemLog;
use App\Services\Accounting\AccountMappingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * AccountMappingService resolution, fallback, update/audit, and validation.
 * account_mappings is seeded by SchemaSeeder with the AccountMappingKey
 * defaults, so the seeded values below are the enum defaults.
 */
class AccountMappingServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected AccountMappingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AccountMappingService::class);
    }

    #[Test]
    public function code_returns_seeded_table_value(): void
    {
        $this->assertSame('1000', $this->service->code(AccountMappingKey::CashMyr));
        $this->assertSame('2000', $this->service->code(AccountMappingKey::InventoryDefault));
    }

    #[Test]
    public function code_falls_back_to_enum_default_when_row_missing(): void
    {
        AccountMapping::where('key', AccountMappingKey::CashPetty->value)->delete();

        $this->assertSame('1050', $this->service->code(AccountMappingKey::CashPetty));
    }

    #[Test]
    public function for_currency_resolves_override_then_default_key(): void
    {
        // No inventory.USD row → falls back to the seeded inventory.default (2000)
        $this->assertSame('2000', $this->service->forCurrency('inventory', 'USD'));

        AccountMapping::create([
            'key' => 'inventory.USD',
            'account_code' => '2001',
        ]);

        $this->assertSame('2001', $this->service->forCurrency('inventory', 'USD'));

        // Other currencies still fall through to the default
        $this->assertSame('2000', $this->service->forCurrency('inventory', 'EUR'));
    }

    #[Test]
    public function for_currency_rejects_unknown_prefix(): void
    {
        $this->expectException(AccountingPeriodException::class);
        $this->service->forCurrency('bogus', 'USD');
    }

    #[Test]
    public function update_persists_override_and_writes_audit_log(): void
    {
        // Warm the cache with the old value so the post-update read must
        // prove the tag invalidation worked, not just a cold lookup.
        $this->assertSame('1000', $this->service->code(AccountMappingKey::CashMyr));

        $this->service->update(
            [AccountMappingKey::CashMyr->value => '1100'],
            null,
            'redirect MYR cash to bank account'
        );

        $row = AccountMapping::where('key', 'cash.myr')->first();
        $this->assertSame('1100', $row->account_code);
        $this->assertSame('1100', $this->service->code(AccountMappingKey::CashMyr));

        $log = SystemLog::where('action', 'account_mapping_updated')
            ->where('entity_type', 'AccountMapping')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('cash.myr', $log->new_values['key']);
        $this->assertSame('1100', $log->new_values['account_code']);
        $this->assertSame('1000', $log->old_values['account_code']);
        $this->assertSame('redirect MYR cash to bank account', $log->new_values['reason']);
    }

    #[Test]
    public function update_rejects_unsetting_a_fixed_key(): void
    {
        $this->expectException(AccountingPeriodException::class);
        $this->service->update([AccountMappingKey::CashMyr->value => null], null);
    }

    #[Test]
    public function update_removes_dynamic_currency_override_and_audits(): void
    {
        AccountMapping::create(['key' => 'inventory.USD', 'account_code' => '2001']);

        $this->service->update(['inventory.USD' => null], null, 'revert to pooled inventory');

        $this->assertNull(AccountMapping::where('key', 'inventory.USD')->first());
        $this->assertSame('2000', $this->service->forCurrency('inventory', 'USD'));

        $log = SystemLog::where('action', 'account_mapping_removed')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('inventory.USD', $log->new_values['key']);
    }

    #[Test]
    public function update_rejects_unknown_key(): void
    {
        $this->expectException(AccountingPeriodException::class);
        $this->service->update(['totally.bogus' => '1000'], null);
    }

    #[Test]
    public function validate_account_rejects_wrong_account_type(): void
    {
        // 5000 is a Revenue account; cash.myr requires Asset.
        $this->expectException(AccountingPeriodException::class);
        $this->service->validateAccount(AccountMappingKey::CashMyr->value, '5000');
    }

    #[Test]
    public function validate_account_rejects_unknown_code(): void
    {
        $this->expectException(AccountingPeriodException::class);
        $this->service->validateAccount(AccountMappingKey::CashMyr->value, '99999');
    }

    #[Test]
    public function effective_mappings_marks_overrides_versus_defaults(): void
    {
        AccountMapping::where('key', AccountMappingKey::CashMyr->value)
            ->update(['account_code' => '1100']);

        $effective = $this->service->effectiveMappings();

        $fixed = collect($effective['fixed'])->keyBy(fn ($row) => $row['key']->value);
        $this->assertSame('1100', $fixed['cash.myr']['account_code']);
        $this->assertFalse($fixed['cash.myr']['is_default']);
        $this->assertTrue($fixed['cash.petty']['is_default']);
    }
}
