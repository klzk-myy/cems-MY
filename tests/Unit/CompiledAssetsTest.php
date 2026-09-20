<?php

namespace Tests\Unit;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class CompiledAssetsTest extends TestCase
{
    /**
     * Tailwind v4 generates utilities at build time, so a view that adds a
     * class not present in the compiled CSS renders unstyled. Fail loudly
     * when public/build is older than the newest file under resources/.
     */
    #[Test]
    public function compiled_assets_are_newer_than_frontend_sources(): void
    {
        $buildDir = public_path('build');

        $this->assertDirectoryExists(
            $buildDir,
            'public/build is missing — run `npm run build`'
        );

        $latestBuild = $this->latestMtime($buildDir);
        $latestSource = max(
            $this->latestMtime(resource_path('views')),
            $this->latestMtime(resource_path('css')),
            $this->latestMtime(resource_path('js')),
        );

        $this->assertGreaterThanOrEqual(
            $latestSource,
            $latestBuild,
            'Compiled assets are stale — run `npm run build` '
            .'(newest source: '.date('c', $latestSource)
            .', newest build: '.date('c', $latestBuild).')'
        );
    }

    private function latestMtime(string $dir): int
    {
        $latest = 0;

        foreach (new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        ) as $file) {
            $latest = max($latest, $file->getMTime());
        }

        return $latest;
    }
}
