<?php

namespace Tests\Unit;

use App\Services\System\QueryLoggingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QueryLoggingServiceTest extends TestCase
{
    #[Test]
    public function it_warns_when_same_query_repeats_with_different_ids(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->with('Potential N+1 query detected', \Mockery::subset([
                'count' => 3,
                'unique_ids' => 3,
            ]));

        $service = new QueryLoggingService;

        $queries = [
            ['query' => 'select * from customers where id = ?', 'bindings' => [1]],
            ['query' => 'select * from customers where id = ?', 'bindings' => [2]],
            ['query' => 'select * from customers where id = ?', 'bindings' => [3]],
        ];

        DB::shouldReceive('getQueryLog')->once()->andReturn($queries);

        $service->analyzeAndLog(Request::create('/test'));
    }

    #[Test]
    public function it_does_not_warn_for_identical_repeated_queries(): void
    {
        Log::shouldReceive('warning')->never();

        $service = new QueryLoggingService;

        $queries = [
            ['query' => 'select count(*) from transactions', 'bindings' => []],
            ['query' => 'select count(*) from transactions', 'bindings' => []],
            ['query' => 'select count(*) from transactions', 'bindings' => []],
        ];

        DB::shouldReceive('getQueryLog')->once()->andReturn($queries);

        $service->analyzeAndLog(Request::create('/test'));
    }
}
