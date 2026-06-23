<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PerformanceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_customer_index_query_count_is_bounded(): void
    {
        // Seed enough customers to test query count
        Customer::factory()->count(50)->create();

        DB::enableQueryLog();

        $user = User::factory()->create(['role' => UserRole::Manager]);
        $this->actingAs($user);

        $response = $this->get('/customers');
        $response->assertOk();

        $queryCount = count(DB::getQueryLog());
        $this->assertLessThan(20, $queryCount, "Customer index should use fewer than 20 queries, used {$queryCount}");

        DB::disableQueryLog();
    }
}
