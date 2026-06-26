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

        // Ensure cache invalidation is deferred until after the DB transaction commits.
        $this->assertStringContainsString('DB::afterCommit(function () {', $content);
        $this->assertStringContainsString("\$this->cacheTagsService->invalidate('dashboard');", $content);

        $afterCommitPos = strpos($content, 'DB::afterCommit(function () {');
        $invalidatePos = strpos($content, "\$this->cacheTagsService->invalidate('dashboard');");

        $this->assertNotFalse($afterCommitPos, 'DB::afterCommit not found');
        $this->assertNotFalse($invalidatePos, 'Cache invalidate not found');
        $this->assertGreaterThan(
            $afterCommitPos,
            $invalidatePos,
            'Dashboard cache invalidation should be inside the DB::afterCommit callback'
        );
    }
}
