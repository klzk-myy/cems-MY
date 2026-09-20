<?php

namespace Tests\Feature\Audit;

use App\Models\StockTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_transfer_number_uses_lock_and_retry(): void
    {
        $date = now()->format('Ymd');

        $first = StockTransfer::generateTransferNumber();
        $this->assertSame("TRF-{$date}-0001", $first);

        // Persisting the first number must bump the sequence for the next call.
        StockTransfer::factory()->create(['transfer_number' => $first]);

        $second = StockTransfer::generateTransferNumber();
        $this->assertSame("TRF-{$date}-0002", $second);
        $this->assertNotSame($first, $second);
    }
}
