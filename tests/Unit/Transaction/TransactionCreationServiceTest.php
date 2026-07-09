<?php

namespace Tests\Unit\Transaction;

use App\Enums\CddLevel;
use App\Enums\TransactionType;
use App\Models\Customer;
use App\Models\TillBalance;
use App\Services\Transaction\DTOs\TransactionCreationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionCreationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function createContext(
        Customer $customer,
        TillBalance $tillBalance,
        CddLevel $cddLevel = CddLevel::Standard,
        bool $holdRequired = false,
        array $dataOverrides = [],
        $allocation = null
    ): TransactionCreationContext {
        $data = [
            'type' => TransactionType::Buy->value,
            'currency_code' => 'USD',
            'amount_foreign' => '1000.00',
            'rate' => '4.5000',
            'purpose' => 'Test purpose',
            'source_of_funds' => 'Business income',
            'customer_id' => $customer->id,
            'till_id' => $tillBalance->till_id,
            'idempotency_key' => null,
        ];
        $data = array_merge($data, $dataOverrides);

        return new TransactionCreationContext(
            data: $data,
            customer: $customer,
            tillBalance: $tillBalance,
            cddLevel: $cddLevel,
            holdRequired: $holdRequired,
            allocation: $allocation
        );
    }

    #[Test]
    public function create_successful_buy_transaction_creates_completed_record(): void
    {
        $this->markTestIncomplete();
    }

    #[Test]
    public function create_successful_sell_transaction_creates_completed_record(): void
    {
        $this->markTestIncomplete();
    }

    #[Test]
    public function create_with_hold_creates_pending_approval_transaction(): void
    {
        $this->markTestIncomplete();
    }

    #[Test]
    public function create_returns_existing_transaction_when_idempotency_key_matches(): void
    {
        $this->markTestIncomplete();
    }

    #[Test]
    public function create_throws_duplicate_transaction_exception_when_recent_duplicate_detected(): void
    {
        $this->markTestIncomplete();
    }

    #[Test]
    public function create_throws_insufficient_stock_exception_when_sell_balance_low(): void
    {
        $this->markTestIncomplete();
    }

    #[Test]
    public function create_throws_till_balance_missing_exception_when_myr_balance_absent(): void
    {
        $this->markTestIncomplete();
    }

    #[Test]
    public function create_reserves_stock_when_pending_approval_sell(): void
    {
        $this->markTestIncomplete();
    }

    #[Test]
    public function create_updates_teller_allocation_for_buy(): void
    {
        $this->markTestIncomplete();
    }

    #[Test]
    public function create_updates_teller_allocation_for_sell(): void
    {
        $this->markTestIncomplete();
    }

    #[Test]
    public function create_creates_accounting_entries_for_simplified_standard_cdd(): void
    {
        $this->markTestIncomplete();
    }

    #[Test]
    public function create_does_not_create_accounting_entries_for_enhanced_cdd_pending(): void
    {
        $this->markTestIncomplete();
    }

    #[Test]
    public function create_logs_audit_with_correct_context(): void
    {
        $this->markTestIncomplete();
    }

    #[Test]
    public function create_dispatches_transaction_created_event_after_commit(): void
    {
        $this->markTestIncomplete();
    }

    #[Test]
    public function create_invalidates_dashboard_cache_after_commit(): void
    {
        $this->markTestIncomplete();
    }
}
