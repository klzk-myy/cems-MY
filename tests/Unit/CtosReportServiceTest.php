<?php

namespace Tests\Unit;

use App\Models\Transaction;
use App\Services\AuditService;
use App\Services\CtosReportService;
use App\Services\MathService;
use App\Services\ThresholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CtosReportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CtosReportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $mockThreshold = Mockery::mock(ThresholdService::class);
        $mockThreshold->shouldReceive('getCtosThreshold')->andReturn('25000');
        $this->service = new CtosReportService(
            app(AuditService::class),
            app(MathService::class),
            $mockThreshold
        );
    }

    public function test_ctos_threshold_is_25000(): void
    {
        $transaction = Transaction::factory()->create([
            'amount_local' => 24999,
        ]);

        $this->assertFalse($this->service->qualifiesForCtos($transaction));

        $transaction2 = Transaction::factory()->create([
            'amount_local' => 25000,
        ]);

        $this->assertTrue($this->service->qualifiesForCtos($transaction2));
    }

    public function test_qualifies_for_ctos_returns_false_for_small_amount(): void
    {
        $transaction = Transaction::factory()->create([
            'amount_local' => 5000,
        ]);

        $this->assertFalse($this->service->qualifiesForCtos($transaction));
    }

    public function test_qualifies_for_ctos_returns_true_at_threshold(): void
    {
        $transaction = Transaction::factory()->create([
            'amount_local' => 25000,
        ]);

        $this->assertTrue($this->service->qualifiesForCtos($transaction));
    }

    public function test_qualifies_for_ctos_returns_true_above_threshold(): void
    {
        $transaction = Transaction::factory()->create([
            'amount_local' => 50000,
        ]);

        $this->assertTrue($this->service->qualifiesForCtos($transaction));
    }
}
