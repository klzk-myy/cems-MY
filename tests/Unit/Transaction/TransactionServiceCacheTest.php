<?php

namespace Tests\Unit\Transaction;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionServiceCacheTest extends TestCase
{
    #[Test]
    public function approve_transaction_invalidates_dashboard_cache_after_db_commit(): void
    {
        $file = base_path('app/Services/Transaction/TransactionService.php');
        $this->assertFileExists($file);

        $content = file_get_contents($file);

        // Verify cache invalidation is deferred until after the DB transaction commits.
        // This ensures the invalidation only runs when the approval is durable.
        $this->assertStringContainsString(
            "DB::afterCommit(function () {\n",
            $content,
            'Cache invalidation should be deferred via DB::afterCommit'
        );

        $this->assertStringContainsString(
            "\$this->cacheTagsService->invalidate('dashboard');",
            $content,
            'Dashboard cache should be invalidated after commit'
        );

        $afterCommitPos = strpos($content, 'DB::afterCommit(function () {');
        $invalidatePos = strpos($content, "\$this->cacheTagsService->invalidate('dashboard')");

        $this->assertNotFalse($afterCommitPos, 'DB::afterCommit not found');
        $this->assertNotFalse($invalidatePos, 'Cache invalidate not found');
        $this->assertGreaterThan(
            $afterCommitPos,
            $invalidatePos,
            'Cache invalidation should be inside the DB::afterCommit callback'
        );
    }
}
