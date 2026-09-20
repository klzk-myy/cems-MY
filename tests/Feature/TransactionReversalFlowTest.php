<?php

namespace Tests\Feature;

use App\Enums\JournalEntryStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\TellerAllocation;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Branch\TillBalanceManager;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('slow')]
class TransactionReversalFlowTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(VerifyCsrfToken::class);

        Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'is_active' => true]);
        Currency::firstOrCreate(['code' => 'MYR'], ['name' => 'Malaysian Ringgit', 'symbol' => 'RM', 'decimal_places' => 2, 'is_active' => true]);
    }

    #[Test]
    public function reversal_restores_books_and_refund_completes_without_double_booking(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $customer = $this->createTestCustomer();
        $counter = $this->setupOpenTill($teller, 'USD', '1000.00');
        $manager = User::factory()->for($teller->branch)->create(['role' => UserRole::Manager]);
        $compliance = User::factory()->for($teller->branch)->create(['role' => UserRole::ComplianceOfficer]);

        // Fixture represents a real Completed Sell: 100 USD out at 4.60.
        // Position and allocation are at their post-booking values; the till
        // legs are applied for real so the aggregate counters are accurate.
        $position = CurrencyPosition::create([
            'currency_code' => 'USD',
            'branch_id' => $counter->branch_id,
            'quantity' => '400.00',
            'average_cost' => '4.40',
        ]);

        $usdTill = TillBalance::where('till_id', (string) $counter->code)->where('currency_code', 'USD')->firstOrFail();
        $myrTill = TillBalance::where('till_id', (string) $counter->code)->where('currency_code', 'MYR')->firstOrFail();
        app(TillBalanceManager::class)->applyTransaction($usdTill, TransactionType::Sell, '460.00', '100.00');

        $allocation = TellerAllocation::where('user_id', $teller->id)->where('currency_code', 'USD')->firstOrFail();
        $allocation->update(['current_quantity' => '900.00']);

        $journalEntry = JournalEntry::create([
            'entry_date' => now()->toDateString(),
            'description' => 'Sell 100 USD @ 4.60',
            'status' => JournalEntryStatus::Posted->value,
            'posted_by' => $teller->id,
            'reference_type' => 'Transaction',
            'reference_id' => null, // set after the transaction exists
        ]);

        $transaction = Transaction::factory()->create([
            'type' => TransactionType::Sell,
            'currency_code' => 'USD',
            'quantity' => '100.00',
            'amount_myr' => '460.00',
            'rate' => '4.60',
            'customer_id' => $customer->id,
            'user_id' => $teller->id,
            'branch_id' => $counter->branch_id,
            'till_id' => (string) $counter->id,
            'status' => TransactionStatus::Completed,
            'cdd_level' => 'Simplified',
            'idempotency_key' => uniqid('test_', true),
        ]);

        $journalEntry->update(['reference_id' => $transaction->id]);
        JournalLine::create(['journal_entry_id' => $journalEntry->id, 'account_code' => '1000', 'debit' => '460.00', 'credit' => '0.00', 'description' => 'MYR paid to customer']);
        JournalLine::create(['journal_entry_id' => $journalEntry->id, 'account_code' => '2001', 'debit' => '0.00', 'credit' => '460.00', 'description' => 'USD stock out']);

        // --- Manager requests the reversal -----------------------------------
        $this->actingAs($manager)->get("/transactions/{$transaction->id}/reverse")->assertOk();

        $response = $this->actingAs($manager)->post("/transactions/{$transaction->id}/reverse", [
            'reason' => 'Customer disputed the rate after settlement was agreed',
            'confirm_understanding' => 'on',
        ]);

        $transaction->refresh();
        $this->assertSame(TransactionStatus::Reversed, $transaction->status);

        $refund = $transaction->refundTransaction;
        $this->assertNotNull($refund);
        $this->assertTrue((bool) $refund->is_refund);
        $this->assertSame(TransactionType::Buy, $refund->type);
        $this->assertSame(TransactionStatus::PendingApproval, $refund->status);
        $this->assertSame($transaction->id, $refund->original_transaction_id);
        $this->assertNotNull($refund->hold_reason);
        $response->assertRedirect(route('transactions.show', $refund));

        // --- Full chain of custody restored ----------------------------------
        $this->assertEquals(500.0, (float) $position->fresh()->quantity);            // Sell -100 undone
        $usdTill->refresh();
        $this->assertEquals(0.0, (float) $usdTill->sell_quantity);
        $this->assertEquals(0.0, (float) $usdTill->total_quantity);                  // expected balance back to 1000
        $this->assertEquals(0.0, (float) $myrTill->fresh()->transaction_total_myr);  // MYR +460 undone
        $this->assertEquals(1000.0, (float) $allocation->fresh()->current_quantity); // Sell -100 undone

        $journalEntry->refresh();
        $this->assertSame(JournalEntryStatus::Reversed->value, $journalEntry->status->value);
        $reversalEntry = JournalEntry::where('reference_id', $journalEntry->id)->where('reference_type', 'Reversal')->first();
        $this->assertNotNull($reversalEntry);
        $this->assertSame(JournalEntryStatus::Posted->value, $reversalEntry->status->value);

        // --- Refund lifecycle: hold -> clear -> approve -> complete ----------
        // Held refund cannot be approved until compliance clears the hold.
        $this->actingAs($compliance)->post("/transactions/{$refund->id}/approve");
        $this->assertSame(TransactionStatus::PendingApproval, $refund->fresh()->status);

        $this->actingAs($compliance)->post("/transactions/{$refund->id}/clear-hold");
        $this->assertNotNull($refund->fresh()->compliance_cleared_at);

        $this->actingAs($compliance)->post("/transactions/{$refund->id}/approve");
        $this->assertSame(TransactionStatus::Approved, $refund->fresh()->status);

        // Snapshot the books — completion must not re-book any financial leg.
        $positionAfterApprove = (float) $position->fresh()->quantity;
        $tillAfterApprove = (float) $usdTill->fresh()->sell_quantity;
        $myrAfterApprove = (float) $myrTill->fresh()->transaction_total_myr;
        $allocationAfterApprove = (float) $allocation->fresh()->current_quantity;
        $journalCountAfterApprove = JournalEntry::count();

        $this->actingAs($compliance)->post("/transactions/{$refund->id}/complete-refund");
        $this->assertSame(TransactionStatus::Completed, $refund->fresh()->status);

        $this->assertEquals($positionAfterApprove, (float) $position->fresh()->quantity);
        $this->assertEquals($tillAfterApprove, (float) $usdTill->fresh()->sell_quantity);
        $this->assertEquals($myrAfterApprove, (float) $myrTill->fresh()->transaction_total_myr);
        $this->assertEquals($allocationAfterApprove, (float) $allocation->fresh()->current_quantity);
        $this->assertSame($journalCountAfterApprove, JournalEntry::count());
    }

    #[Test]
    public function teller_cannot_reverse_a_transaction(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $otherTeller = User::factory()->for($teller->branch)->create(['role' => UserRole::Teller]);
        $customer = $this->createTestCustomer();
        $counter = $this->setupOpenTill($teller, 'USD', '1000.00');

        $transaction = Transaction::factory()->create([
            'type' => TransactionType::Sell,
            'currency_code' => 'USD',
            'quantity' => '100.00',
            'amount_myr' => '460.00',
            'rate' => '4.60',
            'customer_id' => $customer->id,
            'user_id' => $otherTeller->id,
            'branch_id' => $counter->branch_id,
            'till_id' => (string) $counter->id,
            'status' => TransactionStatus::Completed,
            'cdd_level' => 'Simplified',
            'idempotency_key' => uniqid('test_', true),
        ]);

        $this->actingAs($teller)->get("/transactions/{$transaction->id}/reverse")->assertForbidden();
        $this->actingAs($teller)->post("/transactions/{$transaction->id}/reverse", [
            'reason' => 'Attempting reversal without permission level',
            'confirm_understanding' => 'on',
        ])->assertForbidden();

        $this->assertSame(TransactionStatus::Completed, $transaction->fresh()->status);
    }

    #[Test]
    public function refund_completion_requires_approval_tier(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $customer = $this->createTestCustomer();
        $counter = $this->setupOpenTill($teller, 'USD', '1000.00');
        $manager = User::factory()->for($teller->branch)->create(['role' => UserRole::Manager]);

        CurrencyPosition::create([
            'currency_code' => 'USD',
            'branch_id' => $counter->branch_id,
            'quantity' => '400.00',
            'average_cost' => '4.40',
        ]);

        $transaction = Transaction::factory()->create([
            'type' => TransactionType::Sell,
            'currency_code' => 'USD',
            'quantity' => '100.00',
            'amount_myr' => '460.00',
            'rate' => '4.60',
            'customer_id' => $customer->id,
            'user_id' => $teller->id,
            'branch_id' => $counter->branch_id,
            'till_id' => (string) $counter->id,
            'status' => TransactionStatus::Completed,
            'cdd_level' => 'Simplified',
            'idempotency_key' => uniqid('test_', true),
        ]);

        $this->actingAs($manager)->post("/transactions/{$transaction->id}/reverse", [
            'reason' => 'Customer disputed the rate after settlement was agreed',
            'confirm_understanding' => 'on',
        ]);

        $refund = $transaction->fresh()->refundTransaction;

        // Managers may request the reversal but cannot approve or complete the
        // refund — that stays on the compliance/admin approval tier.
        $this->actingAs($manager)->post("/transactions/{$refund->id}/approve");
        $this->assertSame(TransactionStatus::PendingApproval, $refund->fresh()->status);

        $refund->forceFill([
            'status' => TransactionStatus::Approved,
            'compliance_cleared_at' => now(),
        ])->save();

        $this->actingAs($manager)->post("/transactions/{$refund->id}/complete-refund");
        $this->assertSame(TransactionStatus::Approved, $refund->fresh()->status);
    }

    #[Test]
    public function reversed_transaction_cannot_be_reversed_again(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $customer = $this->createTestCustomer();
        $counter = $this->setupOpenTill($teller, 'USD', '1000.00');
        $manager = User::factory()->for($teller->branch)->create(['role' => UserRole::Manager]);

        $transaction = Transaction::factory()->create([
            'type' => TransactionType::Sell,
            'currency_code' => 'USD',
            'quantity' => '100.00',
            'amount_myr' => '460.00',
            'rate' => '4.60',
            'customer_id' => $customer->id,
            'user_id' => $teller->id,
            'branch_id' => $counter->branch_id,
            'till_id' => (string) $counter->id,
            'status' => TransactionStatus::Reversed,
            'idempotency_key' => uniqid('test_', true),
        ]);

        $this->actingAs($manager)->get("/transactions/{$transaction->id}/reverse")->assertRedirect();
        $this->actingAs($manager)->post("/transactions/{$transaction->id}/reverse", [
            'reason' => 'Attempting to reverse an already reversed transaction',
            'confirm_understanding' => 'on',
        ])->assertRedirect();

        $this->assertSame(TransactionStatus::Reversed, $transaction->fresh()->status);
        $this->assertNull($transaction->fresh()->refundTransaction);
    }
}
