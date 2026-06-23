<?php

namespace Tests\Unit;

use App\Enums\StockReservationStatus;
use App\Models\StockReservation;
use App\Models\Transaction;
use App\Services\Transaction\StockReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StockReleaseServiceTest extends TestCase
{
    use RefreshDatabase;

    protected StockReleaseService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestBranch();
        $this->service = app(StockReleaseService::class);
    }

    #[Test]
    public function release_reservation_releases_pending_stock(): void
    {
        $transaction = Transaction::factory()->create();

        StockReservation::factory()->create([
            'transaction_id' => $transaction->id,
            'status' => StockReservationStatus::Pending,
        ]);

        $this->service->releaseReservation($transaction);

        $reservation = StockReservation::where('transaction_id', $transaction->id)->first();
        $this->assertEquals(StockReservationStatus::Released, $reservation->status);
    }

    #[Test]
    public function release_reservation_does_nothing_when_no_reservation(): void
    {
        $transaction = Transaction::factory()->create();

        $this->service->releaseReservation($transaction);

        $reservation = StockReservation::where('transaction_id', $transaction->id)->first();
        $this->assertNull($reservation);
    }
}
