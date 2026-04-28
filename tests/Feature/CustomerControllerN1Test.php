<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomerControllerN1Test extends TestCase
{
    use RefreshDatabase;

    public function test_customer_index_uses_eager_loading()
    {
        $user = User::factory()->create();
        Customer::factory()->count(10)->create();

        DB::enableQueryLog();
        $response = $this->actingAs($user)->get('/customers');
        $queries = DB::getQueryLog();

        // Should be less than 20 queries (with eager loading)
        $this->assertLessThan(20, count($queries), 'Too many queries detected - possible N+1 problem');
        $response->assertStatus(200);
    }
}
