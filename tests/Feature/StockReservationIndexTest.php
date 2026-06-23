<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StockReservationIndexTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function stock_reservations_has_transaction_id_index()
    {
        $this->assertTrue(
            Schema::hasIndex('stock_reservations', 'stock_reservations_transaction_id_index'),
            'stock_reservations table should have transaction_id index'
        );
    }
}
