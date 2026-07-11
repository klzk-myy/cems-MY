<?php

namespace Tests\Unit\Views;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrphanedViewsRetentionTest extends TestCase
{
    /**
     * Views confirmed as unused across app, resources, routes, config, database and tests.
     *
     * These were identified by scripts/find-orphaned-views.php and manually verified.
     *
     * @var array<int, string>
     */
    protected array $confirmedOrphanedViews = [
        // Add confirmed unused views here. Both previously-listed views are still
        // consumed by the application (EodReconciliationController / GenerateEodReconciliation
        // and TransactionController::receipt), so they have been removed from this list.
    ];

    #[Test]
    public function orphaned_views_retention_folder_exists(): void
    {
        $this->assertDirectoryExists(
            resource_path('views/orphaned'),
            'Orphaned views retention folder must exist.'
        );
    }

    #[Test]
    public function confirmed_orphaned_views_are_in_retention_folder(): void
    {
        $missing = [];

        foreach ($this->confirmedOrphanedViews as $view) {
            $path = resource_path('views/orphaned/'.str_replace('.', '/', $view).'.blade.php');
            if (! file_exists($path)) {
                $missing[] = $view;
            }
        }

        $this->assertEmpty(
            $missing,
            'Confirmed orphaned views must be moved to resources/views/orphaned/. Missing: '.implode(', ', $missing)
        );
    }

    #[Test]
    public function confirmed_orphaned_views_are_removed_from_active_views_folder(): void
    {
        $remaining = [];

        foreach ($this->confirmedOrphanedViews as $view) {
            $path = resource_path('views/'.str_replace('.', '/', $view).'.blade.php');
            if (file_exists($path)) {
                $remaining[] = $view;
            }
        }

        $this->assertEmpty(
            $remaining,
            'Confirmed orphaned views must not remain in resources/views/. Remaining: '.implode(', ', $remaining)
        );
    }
}
