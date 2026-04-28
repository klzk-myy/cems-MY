# Phase 2: Performance Optimization Design

**Date:** 2026-04-28
**Project:** CEMS-MY (Currency Exchange Management System)
**Phase:** 2 - Performance Optimization
**Status:** Design Approved

---

## Executive Summary

Phase 2 focuses on comprehensive performance optimization across the CEMS-MY system. This phase addresses query performance, caching strategies, background job optimization, performance monitoring, and load testing. The goal is to achieve 50%+ reduction in average response time, 90%+ cache hit rate, zero N+1 query problems, queue processing time < 5 seconds per job, and support for 1000+ concurrent users.

**Estimated Timeline:** 4-6 days

---

## Objectives

1. Optimize query performance by fixing N+1 problems and adding database indexes
2. Implement Redis caching for frequently accessed data
3. Optimize Horizon configuration and background job performance
4. Add comprehensive performance monitoring with logging, tracking, and dashboard
5. Conduct load testing to identify bottlenecks and validate scalability

---

## Success Criteria

- 50%+ reduction in average response time
- 90%+ cache hit rate for frequently accessed data
- Zero N+1 query problems
- Queue processing time < 5 seconds per job
- System handles 1000+ concurrent users

---

## Sub-Projects

### Sub-Project 1: Query Optimization

**Priority:** High
**Timeline:** 1-2 days

#### 1.1 N+1 Problem Detection & Fixing

**Approach:**
- Use Laravel Debugbar or Telescope to identify N+1 queries
- Focus on high-traffic areas:
  - Transaction listing (index pages)
  - Customer listing with relationships
  - Currency position queries
  - Compliance monitoring queries
- Fix by adding eager loading (`with()`) in controllers/services
- Example: `Transaction::with(['customer', 'currency', 'teller'])->get()`

**Implementation:**
```php
// Before (N+1 problem)
$transactions = Transaction::all();
foreach ($transactions as $transaction) {
    echo $transaction->customer->name; // N+1 query
}

// After (eager loading)
$transactions = Transaction::with(['customer', 'currency', 'teller'])->get();
foreach ($transactions as $transaction) {
    echo $transaction->customer->name; // No additional query
}
```

**Testing:**
- Feature tests for N+1 fixes (verify query count)
- Performance benchmarks before/after optimization
- Load tests to validate improvements

#### 1.2 Database Indexing Strategy

**Approach:**
- Analyze slow query logs (enable `DB::listen()` or use Laravel Telescope)
- Identify queries missing indexes on:
  - `transactions.customer_id` (frequent joins)
  - `transactions.currency_id` (frequent joins)
  - `transactions.status` (frequent filtering)
  - `transactions.created_at` (date range queries)
  - `customers.id_number_hash` (blind index lookups)
  - `stock_reservations.transaction_id` (foreign key lookups)
- Create migration for missing indexes
- Verify index usage with `EXPLAIN` queries

**Implementation:**
```php
// Migration example
Schema::table('transactions', function (Blueprint $table) {
    $table->index(['customer_id', 'currency_id'], 'idx_customer_currency');
    $table->index(['status', 'created_at'], 'idx_status_date');
});

Schema::table('customers', function (Blueprint $table) {
    $table->index('id_number_hash', 'idx_id_number_hash');
});
```

**Testing:**
- Verify index usage with `EXPLAIN`
- Performance benchmarks for indexed queries
- Load tests to validate index effectiveness

#### 1.3 Query Optimization Techniques

**Approach:**
- Use query scopes for reusable query logic
- Implement pagination for large datasets
- Use `select()` to only fetch needed columns
- Add database-level constraints where appropriate

**Implementation:**
```php
// Query scope example
class Transaction extends Model
{
    public function scopeCompleted($query)
    {
        return $query->where('status', TransactionStatus::Completed);
    }

    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }
}

// Usage
$transactions = Transaction::completed()
    ->forCustomer($customerId)
    ->with(['customer', 'currency'])
    ->select(['id', 'amount', 'created_at'])
    ->paginate(50);
```

**Testing:**
- Unit tests for query scopes
- Performance benchmarks for optimized queries
- Load tests to validate improvements

---

### Sub-Project 2: Caching Strategy

**Priority:** High
**Timeline:** 1-2 days

#### 2.1 Redis Configuration

**Approach:**
- Switch from `CACHE_DRIVER=file` to `CACHE_DRIVER=redis`
- Configure Redis connection in `.env` and `config/database.php`
- Set appropriate cache TTL values:
  - Exchange rates: 5 minutes (frequently updated)
  - Currency positions: 1 minute (highly dynamic)
  - Customer data: 30 minutes (relatively static)
  - User permissions: 1 hour (rarely changes)

**Implementation:**
```php
// config/database.php
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'),
    'options' => [
        'cluster' => env('REDIS_CLUSTER', 'redis'),
        'prefix' => env('REDIS_PREFIX', 'cems_'),
    ],
    'default' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_DB', '0'),
    ],
],
```

**Testing:**
- Verify Redis connection
- Test cache read/write operations
- Performance benchmarks for cached vs uncached data

#### 2.2 Cache Implementation Pattern

**Approach:**
- Implement service-level caching for frequently accessed data
- Use `Cache::remember()` for read operations
- Use `Cache::put()` for write operations
- Implement cache keys with consistent naming convention

**Implementation:**
```php
// Service-level caching example
class CurrencyPositionService
{
    public function getAvailableBalance(string $currencyCode, int $branchId): string
    {
        return Cache::remember(
            "position:{$branchId}:{$currencyCode}:available",
            now()->addMinute(),
            fn() => $this->calculateAvailableBalance($currencyCode, $branchId)
        );
    }

    public function updatePosition(string $currencyCode, int $branchId, string $amount): void
    {
        // Update database
        $this->calculateAvailableBalance($currencyCode, $branchId);

        // Invalidate cache
        Cache::forget("position:{$branchId}:{$currencyCode}:available");
    }
}

// Exchange rate caching example
class RateManagementService
{
    public function getCurrentRate(string $fromCurrency, string $toCurrency): ?string
    {
        return Cache::remember(
            "rate:{$fromCurrency}:{$toCurrency}",
            now()->addMinutes(5),
            fn() => ExchangeRate::where('from_currency', $fromCurrency)
                ->where('to_currency', $toCurrency)
                ->latest()
                ->value('rate')
        );
    }
}

// Customer data caching example
class CustomerService
{
    public function getCustomer(int $customerId): ?Customer
    {
        return Cache::remember(
            "customer:{$customerId}",
            now()->addMinutes(30),
            fn() => Customer::with(['relations', 'documents'])->find($customerId)
        );
    }
}

// User permissions caching example
class UserService
{
    public function getUserPermissions(int $userId): array
    {
        return Cache::remember(
            "user:{$userId}:permissions",
            now()->addHour(),
            fn() => $this->calculatePermissions($userId)
        );
    }
}
```

**Testing:**
- Unit tests for cache logic
- Integration tests for cache invalidation
- Performance tests to measure cache effectiveness

#### 2.3 Cache Invalidation Strategy

**Approach:**
- Event-driven invalidation using Laravel events
- Tag-based caching for bulk invalidation
- Manual invalidation for critical operations

**Implementation:**
```php
// Event-driven invalidation
class TransactionCreatedListener
{
    public function handle(TransactionCreated $event)
    {
        // Invalidate currency position cache
        Cache::forget("position:{$event->transaction->branch_id}:{$event->transaction->currency_code}:available");
    }
}

// Tag-based caching
class CurrencyPositionService
{
    public function getAllPositions(int $branchId): Collection
    {
        return Cache::tags(['positions', "branch:{$branchId}"])
            ->remember(
                "positions:{$branchId}:all",
                now()->addMinute(),
                fn() => CurrencyPosition::where('branch_id', $branchId)->get()
            );
    }

    public function invalidateAllPositions(int $branchId): void
    {
        Cache::tags(['positions', "branch:{$branchId}"])->flush();
    }
}

// Manual invalidation
class RateManagementService
{
    public function updateRate(string $fromCurrency, string $toCurrency, string $rate): void
    {
        ExchangeRate::updateOrCreate(
            ['from_currency' => $fromCurrency, 'to_currency' => $toCurrency],
            ['rate' => $rate]
        );

        // Invalidate cache
        Cache::forget("rate:{$fromCurrency}:{$toCurrency}");
    }
}
```

**Testing:**
- Unit tests for cache invalidation logic
- Integration tests for event-driven invalidation
- Performance tests to validate cache effectiveness

#### 2.4 Cache Monitoring

**Approach:**
- Track cache hit/miss ratios
- Monitor cache memory usage
- Alert on low cache hit rates (< 80%)

**Implementation:**
```php
// Cache monitoring service
class CacheMonitoringService
{
    public function getCacheStats(): array
    {
        return [
            'hit_rate' => $this->calculateHitRate(),
            'memory_usage' => $this->getMemoryUsage(),
            'keys_count' => $this->getKeysCount(),
        ];
    }

    protected function calculateHitRate(): float
    {
        // Implement hit rate calculation
    }

    protected function getMemoryUsage(): array
    {
        // Implement memory usage tracking
    }

    protected function getKeysCount(): int
    {
        // Implement keys count tracking
    }
}
```

**Testing:**
- Unit tests for monitoring logic
- Integration tests for cache metrics
- Performance tests to validate monitoring accuracy

---

### Sub-Project 3: Background Job Optimization

**Priority:** High
**Timeline:** 1 day

#### 3.1 Horizon Configuration Optimization

**Approach:**
- Review and optimize `config/horizon.php`
- Increase `trim.slaves` for better queue processing
- Adjust `timeout` settings for long-running jobs
- Configure `retry_after` to prevent job duplication
- Set appropriate `memory_limit` for workers

**Implementation:**
```php
// config/horizon.php
'environments' => [
    'production' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default', 'high'],
            'balance' => 'auto',
            'processes' => 10,
            'timeout' => 300,
            'retry_after' => 90,
            'memory_limit' => 128,
        ],
        'supervisor-2' => [
            'connection' => 'redis',
            'queue' => ['low'],
            'balance' => 'auto',
            'processes' => 5,
            'timeout' => 600,
            'retry_after' => 120,
            'memory_limit' => 256,
        ],
    ],
],

'trim' => [
    'recent' => 60,
    'failed' => 10080, // 7 days
    'monitored' => 10080,
],
```

**Testing:**
- Verify Horizon configuration
- Test queue processing with optimized settings
- Performance benchmarks for job execution time

#### 3.2 Job Performance Analysis

**Approach:**
- Identify slow jobs using Horizon dashboard
- Profile jobs with Laravel Telescope
- Focus on:
  - Compliance screening jobs (CTOS, sanctions)
  - Report generation jobs (MSB2, LCTR, STR)
  - Audit hash sealing jobs
  - Email notification jobs

**Implementation:**
```php
// Job profiling example
class ComplianceScreeningJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $customerId
    ) {}

    public function handle(
        ComplianceService $complianceService,
        AuditService $auditService
    ): void {
        $start = microtime(true);

        // Job logic here
        $result = $complianceService->screenCustomer($this->customerId);

        $duration = (microtime(true) - $start) * 1000;

        Log::info('Compliance screening job completed', [
            'customer_id' => $this->customerId,
            'duration_ms' => $duration,
        ]);

        if ($duration > 5000) {
            Log::warning('Slow compliance screening job', [
                'customer_id' => $this->customerId,
                'duration_ms' => $duration,
            ]);
        }
    }
}
```

**Testing:**
- Unit tests for job logic
- Integration tests for queue processing
- Performance benchmarks for job execution time

#### 3.3 Job Optimization Techniques

**Approach:**
- Batch database operations within jobs
- Use chunking for large datasets
- Implement job chaining for dependent operations
- Add job middleware for common functionality
- Use `ShouldBeUnique` interface for idempotent jobs

**Implementation:**
```php
// Batch operations example
class BulkReportGenerationJob implements ShouldQueue
{
    public function handle(ReportingService $reportingService): void
    {
        // Batch database operations
        DB::transaction(function () use ($reportingService) {
            $reportingService->generateDailyReports();
            $reportingService->generateMonthlyReports();
        });
    }
}

// Chunking example
class BulkCustomerUpdateJob implements ShouldQueue
{
    public function handle(CustomerService $customerService): void
    {
        Customer::chunk(100, function ($customers) use ($customerService) {
            foreach ($customers as $customer) {
                $customerService->updateCustomerRisk($customer);
            }
        });
    }
}

// Job chaining example
class TransactionWorkflowJob implements ShouldQueue
{
    public function handle(): void
    {
        TransactionCreatedJob::dispatch($this->transaction)
            ->chain([
                new ComplianceScreeningJob($this->transaction->customer_id),
                new NotificationJob($this->transaction->id),
            ]);
    }
}

// Unique job example
class UniqueReportJob implements ShouldQueue, ShouldBeUnique
{
    public function uniqueId(): string
    {
        return 'report:' . $this->date;
    }
}
```

**Testing:**
- Unit tests for job optimization logic
- Integration tests for job chaining
- Performance benchmarks for optimized jobs

#### 3.4 Queue Strategy

**Approach:**
- Separate queues by priority
- Configure queue workers per priority
- Implement queue monitoring

**Implementation:**
```php
// Queue configuration
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 90,
        'block_for' => null,
    ],
],

'queues' => [
    'high' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'high',
        'retry_after' => 60,
    ],
    'default' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'default',
        'retry_after' => 90,
    ],
    'low' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'low',
        'retry_after' => 120,
    ],
],

// Job dispatch with priority
ComplianceScreeningJob::dispatch($customerId)->onQueue('high');
ReportGenerationJob::dispatch($date)->onQueue('default');
CleanupJob::dispatch()->onQueue('low');
```

**Testing:**
- Unit tests for queue logic
- Integration tests for queue processing
- Performance benchmarks for queue throughput

---

### Sub-Project 4: Performance Monitoring

**Priority:** Medium
**Timeline:** 1 day

#### 4.1 Query Logging

**Approach:**
- Enable query logging in development
- Log slow queries (> 100ms) to dedicated channel
- Use Laravel Telescope for query analysis
- Create custom middleware to log query counts per request

**Implementation:**
```php
// config/database.php
'logging' => env('DB_LOGGING', true),

// Query logging middleware
class QueryLoggingMiddleware
{
    public function handle($request, Closure $next)
    {
        if (config('database.logging')) {
            DB::enableQueryLog();
        }

        $response = $next($request);

        if (config('database.logging')) {
            $queries = DB::getQueryLog();
            $slowQueries = collect($queries)->filter(fn($q) => $q['time'] > 100);

            if ($slowQueries->isNotEmpty()) {
                Log::channel('slow_queries')->warning('Slow queries detected', [
                    'url' => $request->url(),
                    'query_count' => count($queries),
                    'slow_query_count' => $slowQueries->count(),
                    'queries' => $slowQueries->toArray(),
                ]);
            }
        }

        return $response;
    }
}
```

**Testing:**
- Unit tests for logging logic
- Integration tests for query logging
- Performance tests to validate logging overhead

#### 4.2 Response Time Tracking

**Approach:**
- Add performance tracking middleware
- Track API endpoint performance
- Monitor slow endpoints (> 500ms)
- Alert on performance degradation

**Implementation:**
```php
// Performance tracking middleware
class PerformanceTrackingMiddleware
{
    public function handle($request, Closure $next)
    {
        $start = microtime(true);
        $response = $next($request);
        $duration = (microtime(true) - $start) * 1000;

        Log::info('Request performance', [
            'url' => $request->url(),
            'method' => $request->method(),
            'duration_ms' => $duration,
            'status' => $response->status(),
        ]);

        if ($duration > 500) {
            Log::warning('Slow endpoint detected', [
                'url' => $request->url(),
                'method' => $request->method(),
                'duration_ms' => $duration,
            ]);
        }

        return $response;
    }
}

// Register middleware
// app/Http/Kernel.php
protected $middleware = [
    // ... other middleware
    \App\Http\Middleware\PerformanceTrackingMiddleware::class,
];
```

**Testing:**
- Unit tests for tracking logic
- Integration tests for performance tracking
- Load tests to validate tracking accuracy

#### 4.3 Performance Dashboard

**Approach:**
- Create dedicated performance monitoring page
- Display metrics:
  - Average response time (last hour/day)
  - Slowest endpoints
  - Query count per request
  - Cache hit/miss ratio
  - Queue job processing time
- Use Laravel Telescope or custom dashboard
- Real-time updates with WebSockets

**Implementation:**
```php
// Performance monitoring controller
class PerformanceMonitoringController extends Controller
{
    public function index()
    {
        return view('performance.index', [
            'metrics' => [
                'avg_response_time' => $this->getAverageResponseTime(),
                'slowest_endpoints' => $this->getSlowestEndpoints(),
                'avg_query_count' => $this->getAverageQueryCount(),
                'cache_hit_rate' => $this->getCacheHitRate(),
                'queue_processing_time' => $this->getQueueProcessingTime(),
            ],
        ]);
    }

    protected function getAverageResponseTime(): float
    {
        // Implement average response time calculation
    }

    protected function getSlowestEndpoints(): array
    {
        // Implement slowest endpoints tracking
    }

    protected function getAverageQueryCount(): float
    {
        // Implement average query count calculation
    }

    protected function getCacheHitRate(): float
    {
        // Implement cache hit rate calculation
    }

    protected function getQueueProcessingTime(): float
    {
        // Implement queue processing time calculation
    }
}
```

**Testing:**
- Unit tests for dashboard logic
- Integration tests for dashboard data
- Performance tests to validate dashboard accuracy

#### 4.4 Alerting

**Approach:**
- Configure alerts for:
  - Response time > 1s (warning), > 2s (critical)
  - Cache hit rate < 80%
  - Queue backlog > 100 jobs
  - Error rate > 1%

**Implementation:**
```php
// Alerting service
class PerformanceAlertingService
{
    public function checkAlerts(): void
    {
        $this->checkResponseTime();
        $this->checkCacheHitRate();
        $this->checkQueueBacklog();
        $this->checkErrorRate();
    }

    protected function checkResponseTime(): void
    {
        $avgResponseTime = $this->getAverageResponseTime();

        if ($avgResponseTime > 2000) {
            $this->sendCriticalAlert('Response time critical', [
                'avg_response_time_ms' => $avgResponseTime,
            ]);
        } elseif ($avgResponseTime > 1000) {
            $this->sendWarningAlert('Response time warning', [
                'avg_response_time_ms' => $avgResponseTime,
            ]);
        }
    }

    protected function checkCacheHitRate(): void
    {
        $cacheHitRate = $this->getCacheHitRate();

        if ($cacheHitRate < 80) {
            $this->sendWarningAlert('Cache hit rate low', [
                'cache_hit_rate' => $cacheHitRate,
            ]);
        }
    }

    protected function checkQueueBacklog(): void
    {
        $queueBacklog = $this->getQueueBacklog();

        if ($queueBacklog > 100) {
            $this->sendWarningAlert('Queue backlog high', [
                'queue_backlog' => $queueBacklog,
            ]);
        }
    }

    protected function checkErrorRate(): void
    {
        $errorRate = $this->getErrorRate();

        if ($errorRate > 1) {
            $this->sendCriticalAlert('Error rate high', [
                'error_rate' => $errorRate,
            ]);
        }
    }
}
```

**Testing:**
- Unit tests for alerting logic
- Integration tests for alert delivery
- Performance tests to validate alerting accuracy

---

### Sub-Project 5: Load Testing

**Priority:** Medium
**Timeline:** 1 day

#### 5.1 Load Testing Tools

**Approach:**
- Use Laravel's built-in testing with `php artisan test --parallel`
- Integrate with tools like:
  - Apache JMeter (for HTTP load testing)
  - k6 (for modern load testing)
  - Laravel Octane (for performance benchmarking)
- Create load test scenarios for critical endpoints

**Implementation:**
```php
// Load test example using Laravel
class TransactionLoadTest extends TestCase
{
    public function test_concurrent_transaction_creation()
    {
        $this->withoutExceptionHandling();

        $responses = [];
        $concurrentRequests = 100;

        for ($i = 0; $i < $concurrentRequests; $i++) {
            $responses[] = $this->postJson('/api/v1/transactions', [
                'customer_id' => 1,
                'currency_code' => 'USD',
                'amount' => '1000.00',
                'type' => 'buy',
            ]);
        }

        foreach ($responses as $response) {
            $response->assertStatus(201);
        }
    }
}
```

**Testing:**
- Automated load test suite
- Performance regression tests
- Continuous monitoring in CI/CD

#### 5.2 Test Scenarios

**Approach:**
- Create load test scenarios for critical endpoints
- Test with incremental load
- Measure response time degradation

**Implementation:**
```php
// Load test scenarios
class LoadTestScenarios
{
    public function testTransactionCreation(): void
    {
        // 100 concurrent users creating transactions
    }

    public function testTransactionListing(): void
    {
        // 500 concurrent users viewing transaction lists
    }

    public function testCustomerSearch(): void
    {
        // 200 concurrent users searching customers
    }

    public function testDashboardLoading(): void
    {
        // 300 concurrent users loading dashboard
    }

    public function testApiEndpoints(): void
    {
        // 1000 concurrent API requests
    }
}
```

**Testing:**
- Automated load test suite
- Performance regression tests
- Continuous monitoring in CI/CD

#### 5.3 Bottleneck Identification

**Approach:**
- Monitor system resources during load tests
- Identify slow endpoints and queries
- Profile memory leaks

**Implementation:**
```php
// Bottleneck identification service
class BottleneckIdentificationService
{
    public function identifyBottlenecks(): array
    {
        return [
            'slow_endpoints' => $this->getSlowEndpoints(),
            'slow_queries' => $this->getSlowQueries(),
            'memory_leaks' => $this->getMemoryLeaks(),
            'resource_usage' => $this->getResourceUsage(),
        ];
    }

    protected function getSlowEndpoints(): array
    {
        // Implement slow endpoint identification
    }

    protected function getSlowQueries(): array
    {
        // Implement slow query identification
    }

    protected function getMemoryLeaks(): array
    {
        // Implement memory leak identification
    }

    protected function getResourceUsage(): array
    {
        // Implement resource usage tracking
    }
}
```

**Testing:**
- Unit tests for identification logic
- Integration tests for bottleneck detection
- Load tests to validate identification accuracy

#### 5.4 Scalability Validation

**Approach:**
- Test with incremental load: 100 → 500 → 1000 → 2000 concurrent users
- Validate system handles 1000+ concurrent users
- Measure response time degradation
- Identify breaking points

**Implementation:**
```php
// Scalability validation test
class ScalabilityValidationTest extends TestCase
{
    public function test_scalability_validation()
    {
        $loadLevels = [100, 500, 1000, 2000];

        foreach ($loadLevels as $load) {
            $results = $this->runLoadTest($load);

            $this->assertLessThan(1000, $results['avg_response_time'], "Response time too high at {$load} concurrent users");
            $this->assertLessThan(5, $results['error_rate'], "Error rate too high at {$load} concurrent users");
        }
    }

    protected function runLoadTest(int $concurrentUsers): array
    {
        // Implement load test logic
    }
}
```

**Testing:**
- Automated scalability test suite
- Performance regression tests
- Continuous monitoring in CI/CD

#### 5.5 Stress Testing

**Approach:**
- Test beyond normal load (2000+ concurrent users)
- Identify failure modes
- Test graceful degradation
- Validate error handling under load

**Implementation:**
```php
// Stress test example
class StressTest extends TestCase
{
    public function test_stress_testing()
    {
        $extremeLoad = 5000;

        $results = $this->runLoadTest($extremeLoad);

        // System should handle extreme load gracefully
        $this->assertLessThan(10, $results['error_rate'], 'Error rate too high under extreme load');
        $this->assertNotNull($results['response_time'], 'System should respond under extreme load');
    }
}
```

**Testing:**
- Automated stress test suite
- Performance regression tests
- Continuous monitoring in CI/CD

#### 5.6 Performance Baseline

**Approach:**
- Establish baseline metrics before optimization
- Document current performance
- Track improvements over time

**Implementation:**
```php
// Performance baseline service
class PerformanceBaselineService
{
    public function establishBaseline(): array
    {
        return [
            'avg_response_time' => $this->getAverageResponseTime(),
            'max_concurrent_users' => $this->getMaxConcurrentUsers(),
            'query_performance' => $this->getQueryPerformance(),
            'cache_effectiveness' => $this->getCacheEffectiveness(),
            'queue_performance' => $this->getQueuePerformance(),
        ];
    }

    protected function getAverageResponseTime(): float
    {
        // Implement average response time measurement
    }

    protected function getMaxConcurrentUsers(): int
    {
        // Implement max concurrent users measurement
    }

    protected function getQueryPerformance(): array
    {
        // Implement query performance measurement
    }

    protected function getCacheEffectiveness(): array
    {
        // Implement cache effectiveness measurement
    }

    protected function getQueuePerformance(): array
    {
        // Implement queue performance measurement
    }
}
```

**Testing:**
- Unit tests for baseline logic
- Integration tests for baseline accuracy
- Performance tests to validate baseline

---

## Testing Strategy

### Unit Tests
- Test individual components in isolation
- Mock external dependencies
- Verify business logic correctness

### Integration Tests
- Test component interactions
- Verify data flow between services
- Validate cache invalidation

### Performance Tests
- Measure response times
- Validate query performance
- Test cache effectiveness

### Load Tests
- Test concurrent user scenarios
- Validate scalability
- Identify bottlenecks

---

## Deliverables

1. Query optimization report with before/after metrics
2. Redis caching layer with cache invalidation strategy
3. Optimized Horizon configuration for queue processing
4. Database index migration
5. Performance monitoring dashboard
6. Load testing results

---

## Risks and Mitigations

### Risk 1: Cache Inconsistency
**Mitigation:** Implement comprehensive cache invalidation strategy with event-driven updates

### Risk 2: Database Index Bloat
**Mitigation:** Monitor index usage and remove unused indexes

### Risk 3: Queue Backlog
**Mitigation:** Implement queue monitoring and auto-scaling

### Risk 4: Performance Regression
**Mitigation:** Implement continuous performance monitoring and alerting

---

## Success Metrics

- 50%+ reduction in average response time
- 90%+ cache hit rate for frequently accessed data
- Zero N+1 query problems
- Queue processing time < 5 seconds per job
- System handles 1000+ concurrent users

---

## Next Steps

1. Review and approve this design document
2. Create detailed implementation plan using writing-plans skill
3. Execute implementation plan
4. Validate success criteria
5. Document results and lessons learned

---

## References

- Laravel Performance Optimization: https://laravel.com/docs/10.x/cache
- Redis Caching: https://redis.io/docs/manual/patterns/
- Laravel Horizon: https://laravel.com/docs/10.x/horizon
- Laravel Telescope: https://laravel.com/docs/10.x/telescope
- Load Testing with k6: https://k6.io/docs/
