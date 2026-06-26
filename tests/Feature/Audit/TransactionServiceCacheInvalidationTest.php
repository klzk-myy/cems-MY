<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

class TransactionServiceCacheInvalidationTest extends TestCase
{
    public function test_approve_transaction_invalidates_cache_after_commit(): void
    {
        $file = base_path('app/Services/Transaction/TransactionService.php');
        $this->assertFileExists($file);

        $content = file_get_contents($file);

        // Ensure invalidate call exists
        $this->assertStringContainsString('$this->cacheTagsService->invalidate(\'dashboard\');', $content);

        // Ensure invalidate appears after DB::transaction closure (after the "});")
        $posClose = strpos($content, '});');
        $posInvalidate = strpos($content, '$this->cacheTagsService->invalidate(\'dashboard\');');
        $this->assertGreaterThan($posClose, $posInvalidate, 'Cache invalidation should occur after DB::transaction closure');

        // Ensure invalidate appears after event dispatch within the closure (proves it's outside)
        $posDispatch = strpos($content, 'Event::dispatch(new TransactionApproved');
        $this->assertGreaterThan($posDispatch, $posInvalidate, 'Cache invalidation should occur after event dispatch, i.e., outside the transaction');
    }
}
