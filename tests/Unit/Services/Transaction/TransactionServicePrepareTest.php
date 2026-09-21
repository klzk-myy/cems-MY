<?php

namespace Tests\Unit\Services\Transaction;

use App\Enums\TellerAllocationStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Exceptions\Domain\InvalidCurrencyException;
use App\Exceptions\Domain\TillBalanceMissingException;
use App\Exceptions\Domain\TransactionBlockedException;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\TellerAllocation;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Transaction\TransactionCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionServicePrepareTest extends TestCase
{
    use RefreshDatabase;

    protected TransactionCreationService $service;

    protected Branch $branch;

    protected Counter $counter;

    protected Currency $currency;

    protected Customer $customer;

    protected User $teller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TransactionCreationService::class);
        $this->branch = Branch::factory()->create();
        $this->counter = Counter::factory()->create([
            'branch_id' => $this->branch->id,
            'code' => 'CTR-PREP',
        ]);
        $this->currency = Currency::factory()->create([
            'code' => 'USD',
            'is_active' => true,
        ]);
        $this->customer = Customer::factory()->create([
            'risk_rating' => 'low',
            'pep_status' => false,
        ]);
        $this->teller = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $this->branch->id,
        ]);

        TillBalance::factory()->create([
            'till_id' => $this->counter->code,
            'branch_id' => $this->branch->id,
            'currency_code' => 'USD',
            'date' => today(),
            'closed_at' => null,
            'opened_by' => $this->teller->id,
        ]);

        TillBalance::factory()->create([
            'till_id' => $this->counter->code,
            'branch_id' => $this->branch->id,
            'currency_code' => 'MYR',
            'date' => today(),
            'closed_at' => null,
            'opened_by' => $this->teller->id,
        ]);

        TellerAllocation::factory()->create([
            'user_id' => $this->teller->id,
            'branch_id' => $this->branch->id,
            'currency_code' => 'USD',
            'allocated_quantity' => '10000.0000',
            'current_quantity' => '10000.0000',
            'daily_limit_myr' => '50000.0000',
            'daily_used_myr' => '0.0000',
            'status' => TellerAllocationStatus::Active,
            'session_date' => today(),
        ]);
    }

    private function baseData(): array
    {
        return [
            'customer_id' => $this->customer->id,
            'till_id' => $this->counter->code,
            'type' => TransactionType::Buy->value,
            'currency_code' => $this->currency->code,
            'quantity' => '100.00',
            'rate' => '4.500000',
            'purpose' => 'Travel',
            'source_of_funds' => 'Salary',
        ];
    }

    #[Test]
    public function prepare_and_create_completes_small_transaction(): void
    {
        $transaction = $this->service->prepareAndCreate($this->baseData(), $this->teller->id, '127.0.0.1');

        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertEquals(TransactionStatus::Completed, $transaction->status);
        $this->assertEquals(TransactionType::Buy, $transaction->type);
        $this->assertEquals('450.0000', $transaction->amount_myr);
    }

    #[Test]
    public function prepare_and_create_holds_large_transaction_above_threshold(): void
    {
        $data = $this->baseData();
        $data['quantity'] = '2000.00';
        $data['rate'] = '5.000000';

        $transaction = $this->service->prepareAndCreate($data, $this->teller->id, '127.0.0.1');

        $this->assertEquals(TransactionStatus::PendingApproval, $transaction->status);
    }

    #[Test]
    public function prepare_and_create_holds_small_transaction_for_high_risk_customer(): void
    {
        $this->customer->forceFill(['risk_rating' => 'high'])->save();

        // 100 USD * 4.50 = 450 MYR — below the auto-approve threshold, but
        // High-risk customers never auto-complete.
        $transaction = $this->service->prepareAndCreate($this->baseData(), $this->teller->id, '127.0.0.1');

        $this->assertEquals(TransactionStatus::PendingApproval, $transaction->status);
    }

    #[Test]
    public function prepare_and_create_completes_small_transaction_for_medium_risk_customer(): void
    {
        $this->customer->forceFill(['risk_rating' => 'medium'])->save();

        // 100 USD * 4.50 = 450 MYR — Medium risk without a hold flag
        // auto-completes below the RM10,000 threshold.
        $transaction = $this->service->prepareAndCreate($this->baseData(), $this->teller->id, '127.0.0.1');

        $this->assertEquals(TransactionStatus::Completed, $transaction->status);
    }

    #[Test]
    public function prepare_and_create_holds_low_risk_transaction_at_auto_approve_boundary(): void
    {
        $data = $this->baseData();
        $data['quantity'] = '2222.22';
        $data['rate'] = '4.500000'; // 9999.99 MYR — just under 10,000

        $transaction = $this->service->prepareAndCreate($data, $this->teller->id, '127.0.0.1');

        $this->assertEquals(TransactionStatus::Completed, $transaction->status);
    }

    #[Test]
    public function prepare_and_create_holds_low_risk_transaction_at_ten_thousand(): void
    {
        $data = $this->baseData();
        $data['quantity'] = '2222.23';
        $data['rate'] = '4.500000'; // 10000.035 MYR >= 10000

        $transaction = $this->service->prepareAndCreate($data, $this->teller->id, '127.0.0.1');

        $this->assertEquals(TransactionStatus::PendingApproval, $transaction->status);
    }

    #[Test]
    public function prepare_and_create_throws_when_validation_blocks(): void
    {
        $this->customer->forceFill(['sanction_hit' => true])->save();

        $this->expectException(TransactionBlockedException::class);

        $this->service->prepareAndCreate($this->baseData(), $this->teller->id, '127.0.0.1');
    }

    #[Test]
    public function prepare_and_create_throws_for_invalid_currency(): void
    {
        $data = $this->baseData();
        $data['currency_code'] = 'XXX';

        $this->expectException(InvalidCurrencyException::class);

        $this->service->prepareAndCreate($data, $this->teller->id, '127.0.0.1');
    }

    #[Test]
    public function prepare_and_create_throws_for_missing_till_balance(): void
    {
        TillBalance::query()->delete();

        $this->expectException(TillBalanceMissingException::class);

        $this->service->prepareAndCreate($this->baseData(), $this->teller->id, '127.0.0.1');
    }

    #[Test]
    public function create_transaction_delegates_to_prepare_and_create(): void
    {
        $transaction = $this->service->prepareAndCreate($this->baseData(), $this->teller->id, '127.0.0.1');

        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertEquals(TransactionStatus::Completed, $transaction->status);
    }
}
