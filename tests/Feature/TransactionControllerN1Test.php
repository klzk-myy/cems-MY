<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TransactionControllerN1Test extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_index_uses_eager_loading()
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();
        $transactions = Transaction::factory()->count(10)->create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
        ]);

        DB::enableQueryLog();
        $response = $this->actingAs($user)->get('/transactions');
        $queries = DB::getQueryLog();

        // With proper eager loading (customer, currency, user, branch), should be ~4-5 queries total
        // Without eager loading would be 1 + 10*4 = 41+ queries
        $this->assertLessThan(10, count($queries), 'Too many queries detected - possible N+1 problem');
        $response->assertStatus(200);
    }
}
