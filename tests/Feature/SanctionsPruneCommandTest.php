<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SanctionsPruneCommandTest extends TestCase
{
    protected string $archiveDir;

    protected string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $base = sys_get_temp_dir().'/cems-prune-'.uniqid();
        $this->archiveDir = $base.'/archive';
        $this->tempDir = $base.'/temp';
        mkdir($this->archiveDir, 0755, true);
        mkdir($this->tempDir, 0755, true);

        config([
            'sanctions.download.archive_directory' => $this->archiveDir,
            'sanctions.download.temp_directory' => $this->tempDir,
            'sanctions.download.archive_retention_days' => 30,
            'sanctions.download.temp_retention_hours' => 24,
        ]);
    }

    protected function tearDown(): void
    {
        foreach ([$this->archiveDir, $this->tempDir] as $dir) {
            foreach (glob($dir.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($dir);
        }
        rmdir(dirname($this->archiveDir));

        parent::tearDown();
    }

    protected function makeFile(string $dir, string $name, int $mtime): string
    {
        $path = $dir.'/'.$name;
        file_put_contents($path, 'data');
        touch($path, $mtime);

        return $path;
    }

    #[Test]
    public function prunes_old_archive_and_temp_files_but_keeps_recent(): void
    {
        $oldArchive = $this->makeFile($this->archiveDir, 'old_archive.json', now()->subDays(31)->getTimestamp());
        $newArchive = $this->makeFile($this->archiveDir, 'new_archive.json', now()->subDays(5)->getTimestamp());
        $oldTemp = $this->makeFile($this->tempDir, 'old_temp.json', now()->subHours(25)->getTimestamp());
        $newTemp = $this->makeFile($this->tempDir, 'new_temp.json', now()->subHours(1)->getTimestamp());

        $this->artisan('sanctions:prune')->assertSuccessful();

        $this->assertFileDoesNotExist($oldArchive);
        $this->assertFileExists($newArchive);
        $this->assertFileDoesNotExist($oldTemp);
        $this->assertFileExists($newTemp);
    }

    #[Test]
    public function dry_run_deletes_nothing(): void
    {
        $oldArchive = $this->makeFile($this->archiveDir, 'old_archive.json', now()->subDays(90)->getTimestamp());
        $oldTemp = $this->makeFile($this->tempDir, 'old_temp.json', now()->subHours(100)->getTimestamp());

        $this->artisan('sanctions:prune', ['--dry-run' => true])
            ->expectsOutputToContain('Would delete')
            ->assertSuccessful();

        $this->assertFileExists($oldArchive);
        $this->assertFileExists($oldTemp);
    }

    #[Test]
    public function succeeds_when_directories_do_not_exist(): void
    {
        config([
            'sanctions.download.archive_directory' => $this->archiveDir.'/missing-a',
            'sanctions.download.temp_directory' => $this->tempDir.'/missing-t',
        ]);

        $this->artisan('sanctions:prune')->assertSuccessful();
    }
}
