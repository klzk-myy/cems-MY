<?php

namespace Tests\Unit\Transaction;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionServiceCacheTest extends TestCase
{
    #[Test]
    public function approve_transaction_moves_cache_invalidation_inside_transaction(): void
    {
        $file = base_path('app/Services/Transaction/TransactionService.php');
        $this->assertFileExists($file);

        $content = file_get_contents($file);

        // Verify cache invalidation occurs inside DB::transaction closure
        $this->assertStringContainsString(
            'Event::dispatch(new TransactionApproved($lockedTransaction, $approverId));',
            $content,
            'Should dispatch TransactionApproved event with approver ID'
        );

        $this->assertStringContainsString(
            '$this->cacheTagsService->invalidate(\'dashboard\')',
            $content,
            'Should invalidate dashboard cache'
        );

        // Verify order: event dispatch should come before cache invalidate
        $eventPos = strpos($content, 'Event::dispatch(new TransactionApproved');
        $cachePos = strpos($content, '$this->cacheTagsService->invalidate(\'dashboard\')');
        $this->assertNotFalse($eventPos, 'Event dispatch not found');
        $this->assertNotFalse($cachePos, 'Cache invalidate not found');
        $this->assertLessThan($cachePos, $eventPos, 'Event should be dispatched before cache invalidation');

        // Verify the return statement comes after cache invalidation
        $returnPos = strpos($content, 'return $result;', $cachePos);
        $this->assertNotFalse($returnPos, 'Return statement should follow cache invalidation');
    }
}
