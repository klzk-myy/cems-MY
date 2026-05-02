<?php

namespace Tests\Feature;

use App\Enums\StockReservationStatus;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\StockReservation;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\ReservationExpiredNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReservationExpireCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_expires_releases_stale_reservations(): void
    {
        $teller = User::factory()->create(['role' => 'teller']);
        Currency::factory()->create(['code' => 'USD']);

        CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'till_id' => 'TILL1',
            'balance' => '1000.00',
            'avg_cost_rate' => '4.2000',
            'last_valuation_rate' => '4.2000',
        ]);

        $transaction = Transaction::factory()->create([
            'user_id' => $teller->id,
            'currency_code' => 'USD',
            'type' => 'Sell',
            'amount_foreign' => '100.00',
            'rate' => '4.2000',
            'amount_local' => '420.00',
            'till_id' => 'TILL1',
            'branch_id' => $teller->branch_id,
            'status' => 'PendingApproval',
        ]);

        $reservation = StockReservation::factory()->create([
            'transaction_id' => $transaction->id,
            'currency_code' => 'USD',
            'till_id' => 'TILL1',
            'amount_foreign' => '100.00',
            'status' => StockReservationStatus::Pending,
            'expires_at' => now()->subHour(),
            'created_by' => $teller->id,
        ]);

        $this->artisan('reservation:expire')
            ->assertSuccessful();

        $reservation->refresh();
        $this->assertEquals(StockReservationStatus::Released, $reservation->status);
    }

    public function test_does_not_expire_future_reservations(): void
    {
        $teller = User::factory()->create(['role' => 'teller']);

        $transaction = Transaction::factory()->create([
            'user_id' => $teller->id,
            'currency_code' => 'USD',
            'type' => 'Sell',
            'amount_foreign' => '100.00',
            'rate' => '4.2000',
            'amount_local' => '420.00',
            'till_id' => 'TILL1',
            'branch_id' => $teller->branch_id,
            'status' => 'PendingApproval',
        ]);

        $reservation = StockReservation::factory()->create([
            'transaction_id' => $transaction->id,
            'currency_code' => 'USD',
            'till_id' => 'TILL1',
            'amount_foreign' => '100.00',
            'status' => StockReservationStatus::Pending,
            'expires_at' => now()->addHour(),
            'created_by' => $teller->id,
        ]);

        $this->artisan('reservation:expire')
            ->assertSuccessful();

        $reservation->refresh();
        $this->assertEquals(StockReservationStatus::Pending, $reservation->status);
    }

    public function test_notifies_teller_on_expiry(): void
    {
        Notification::fake();

        $teller = User::factory()->create(['role' => 'teller']);

        $transaction = Transaction::factory()->create([
            'user_id' => $teller->id,
            'currency_code' => 'USD',
            'type' => 'Sell',
            'amount_foreign' => '100.00',
            'rate' => '4.2000',
            'amount_local' => '420.00',
            'till_id' => 'TILL1',
            'branch_id' => $teller->branch_id,
            'status' => 'PendingApproval',
        ]);

        $reservation = StockReservation::factory()->create([
            'transaction_id' => $transaction->id,
            'currency_code' => 'USD',
            'till_id' => 'TILL1',
            'amount_foreign' => '100.00',
            'status' => StockReservationStatus::Pending,
            'expires_at' => now()->subHour(),
            'created_by' => $teller->id,
        ]);

        $this->artisan('reservation:expire')
            ->assertSuccessful();

        Notification::assertSentTo($teller, ReservationExpiredNotification::class);
    }

    public function test_expire_command_uses_model_is_expired_method(): void
    {
        $teller = User::factory()->create(['role' => 'teller']);

        $transaction = Transaction::factory()->create([
            'user_id' => $teller->id,
            'currency_code' => 'USD',
            'type' => 'Sell',
            'amount_foreign' => '100.00',
            'rate' => '4.2000',
            'amount_local' => '420.00',
            'till_id' => 'TILL1',
            'branch_id' => $teller->branch_id,
            'status' => 'PendingApproval',
        ]);

        // Create a reservation that is consumed but has an expired expires_at
        // The model's isExpired() checks isPending() && expires_at->isPast()
        // So this should NOT be expired even though expires_at is in the past
        $reservation = StockReservation::factory()->create([
            'transaction_id' => $transaction->id,
            'currency_code' => 'USD',
            'till_id' => 'TILL1',
            'amount_foreign' => '100.00',
            'status' => StockReservationStatus::Consumed,
            'expires_at' => now()->subHour(),
            'created_by' => $teller->id,
        ]);

        $this->artisan('reservation:expire')
            ->assertSuccessful();

        $reservation->refresh();
        $this->assertEquals(StockReservationStatus::Consumed, $reservation->status);
    }
}
