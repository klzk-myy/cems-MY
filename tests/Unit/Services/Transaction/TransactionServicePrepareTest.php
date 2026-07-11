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
use App\Services\Transaction\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionServicePrepareTest extends TestCase
{
    use RefreshDatabase;

    protected TransactionService $service;

    protected Branch $branch;

    protected Counter $counter;

    protected Currency $currency;

    protected Customer $customer;

    protected User $teller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TransactionService::class);
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
            'risk_rating' => 'Low',
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
            'allocated_amount' => '10000.0000',
            'current_balance' => '10000.0000',
            'daily_limit_myr' => '50000.0000',
            'daily_used_myr' => '0.0000',
            'status' => TellerAllocationStatus::ACTIVE,
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
            'amount_foreign' => '100.00',
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
        $this->assertEquals('450.0000', $transaction->amount_local);
    }

    #[Test]
    public function prepare_and_create_holds_large_transaction_above_threshold(): void
    {
        $data = $this->baseData();
        $data['amount_foreign'] = '2000.00';
        $data['rate'] = '5.000000';

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
        $transaction = $this->service->createTransaction($this->baseData(), $this->teller->id, '127.0.0.1');

        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertEquals(TransactionStatus::Completed, $transaction->status);
    }
}
