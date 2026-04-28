<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Services\QueryLoggingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class QueryLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_query_logging_can_be_enabled()
    {
        Config::set('database.logging', true);
        $this->assertTrue(Config::get('database.logging'));
    }

    public function test_query_logging_can_be_disabled()
    {
        Config::set('database.logging', false);
        $this->assertFalse(Config::get('database.logging'));
    }

    public function test_queries_are_logged_when_enabled_via_service()
    {
        $service = app(QueryLoggingService::class);
        $service->enable();

        Customer::factory()->create();

        $queries = $service->getQueries();

        $this->assertNotEmpty($queries);
        $this->assertStringContainsString('customers', $queries[0]['query']);
    }

    public function test_queries_are_not_logged_when_disabled_via_service()
    {
        $service = app(QueryLoggingService::class);
        $service->disable();

        Customer::factory()->create();

        $queries = $service->getQueries();

        $this->assertEmpty($queries);
    }

    public function test_n_plus_one_detection_logs_duplicate_queries()
    {
        $service = app(QueryLoggingService::class);
        $service->enable();

        Customer::factory()->count(3)->create();
        $customers = Customer::all();

        foreach ($customers as $customer) {
            $customer->load('transactions');
        }

        $queries = $service->getQueries();
        $customerQueries = array_filter($queries, function ($query) {
            return str_contains($query['query'], 'customers');
        });

        $this->assertGreaterThan(1, count($customerQueries));
    }

    public function test_middleware_logs_queries_when_config_enabled()
    {
        Config::set('database.logging', true);

        $response = $this->get('/test/query-log');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('queries', $data);
        $this->assertNotEmpty($data['queries']);
        $this->assertStringContainsString('customers', $data['queries'][0]['query']);
    }

    public function test_middleware_does_not_log_queries_when_config_disabled()
    {
        Config::set('database.logging', false);

        $response = $this->get('/test/query-log');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('queries', $data);
        $this->assertEmpty($data['queries']);
    }
}
