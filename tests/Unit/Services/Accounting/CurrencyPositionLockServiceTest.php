<?php

namespace Tests\Unit\Services\Accounting;

use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Services\Accounting\CurrencyPositionLockService;
use App\Services\System\MathService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CurrencyPositionLockServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_locks_existing_position(): void
    {
        $position = CurrencyPosition::factory()->create();
        $service = new CurrencyPositionLockService(new MathService);

        $locked = $service->lock($position->branch_id, $position->currency_code);

        $this->assertEquals($position->id, $locked->id);
    }

    public function test_creates_missing_position_via_lock(): void
    {
        $service = new CurrencyPositionLockService(new MathService);

        $locked = DB::transaction(fn () => $service->lock('BRANCH_001', 'EUR'));

        $this->assertDatabaseHas('currency_positions', [
            'branch_id' => 'BRANCH_001',
            'currency_code' => 'EUR',
            'quantity' => '0.0000',
        ]);
        $this->assertSame('0.0000', (string) $locked->quantity);
    }

    public function test_find_for_update_returns_existing_locked_position(): void
    {
        $position = CurrencyPosition::factory()->create();
        $service = new CurrencyPositionLockService(new MathService);

        $locked = DB::transaction(fn () => $service->findForUpdate($position->branch_id, $position->currency_code));

        $this->assertInstanceOf(CurrencyPosition::class, $locked);
        $this->assertEquals($position->id, $locked->id);
    }

    public function test_find_for_update_returns_null_when_no_position_exists(): void
    {
        $service = new CurrencyPositionLockService(new MathService);

        $result = DB::transaction(fn () => $service->findForUpdate('MISSING_BRANCH', 'XXX'));

        $this->assertNull($result);
    }

    public function test_adjusts_position_balance(): void
    {
        $position = CurrencyPosition::factory()->create(['balance' => '1000.0000']);
        $service = new CurrencyPositionLockService(new MathService);

        $service->adjust($position, '100.0000', 'add');

        $this->assertEquals('1100.0000', $position->fresh()->balance);
    }

    public function test_adjust_subtracts_position_balance(): void
    {
        $position = CurrencyPosition::factory()->create(['balance' => '1000.0000']);
        $service = new CurrencyPositionLockService(new MathService);

        $service->adjust($position, '250.0000', 'subtract');

        $this->assertEquals('750.0000', $position->fresh()->balance);
    }

    public function test_invalid_operation_throws_invalid_argument_exception(): void
    {
        $position = CurrencyPosition::factory()->create(['balance' => '1000.0000']);
        $service = new CurrencyPositionLockService(new MathService);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown position operation: multiply');

        $service->adjust($position, '100.0000', 'multiply');
    }

    public function test_lock_falls_back_to_existing_position_on_duplicate_key_race(): void
    {
        $branchId = 'RACE_BRANCH';
        $currencyCode = 'EUR';

        Currency::factory()->create(['code' => $currencyCode, 'is_active' => true]);

        $existingPosition = CurrencyPosition::factory()->create([
            'branch_id' => $branchId,
            'currency_code' => $currencyCode,
            'quantity' => '1000.0000',
        ]);

        $service = \Mockery::mock(CurrencyPositionLockService::class)
            ->makePartial();
        $service->shouldReceive('findForUpdate')
            ->with($branchId, $currencyCode)
            ->once()
            ->andReturnNull();

        $locked = $service->lock($branchId, $currencyCode);

        $this->assertEquals($existingPosition->id, $locked->id);
        $this->assertSame('1000.0000', (string) $locked->quantity);
    }
}
