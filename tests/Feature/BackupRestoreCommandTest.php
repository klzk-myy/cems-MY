<?php

namespace Tests\Feature;

use App\Enums\BackupStatus;
use App\Models\BackupLog;
use App\Services\System\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BackupRestoreCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The restore shell-out itself (mysql client invocation inside
     * BackupService::restoreBackup) cannot run in tests, so the service is
     * mocked and the command's validation / confirmation / dispatch logic is
     * exercised instead.
     */
    protected function createSuccessfulBackup(): BackupLog
    {
        return BackupLog::factory()->create([
            'backup_name' => 'backup-2026-08-26-0200',
            'backup_type' => BackupLog::TYPE_DATABASE,
            'disk' => BackupLog::DISK_LOCAL,
            'file_path' => 'backups/backup-2026-08-26-0200.zip',
            'status' => BackupStatus::Completed,
            'verification_status' => true,
            'verified_at' => now(),
        ]);
    }

    #[Test]
    public function fails_when_backup_log_id_does_not_exist(): void
    {
        $this->artisanCommand('backup:restore', ['id' => 99999])
            ->expectsOutputToContain('not found')
            ->assertFailed();
    }

    #[Test]
    public function refuses_to_restore_from_a_failed_backup(): void
    {
        BackupLog::factory()->create([
            'status' => BackupStatus::Failed,
            'error_message' => 'mysqldump failed',
        ]);

        $this->mock(BackupService::class)->shouldNotReceive('restoreBackup');

        $this->artisanCommand('backup:restore', ['id' => BackupLog::first()->id])
            ->expectsOutputToContain('failed or incomplete')
            ->assertFailed();
    }

    #[Test]
    public function requires_typing_the_backup_name_without_force(): void
    {
        $log = $this->createSuccessfulBackup();

        $this->mock(BackupService::class)->shouldNotReceive('restoreBackup');

        $this->artisanCommand('backup:restore', ['id' => $log->id])
            ->expectsQuestion('Confirmation', 'wrong-name-typed')
            ->expectsOutputToContain('Confirmation failed')
            ->assertFailed();
    }

    #[Test]
    public function restores_successfully_with_force_skipping_the_prompt(): void
    {
        $log = $this->createSuccessfulBackup();

        $this->mock(BackupService::class)
            ->shouldReceive('restoreBackup')
            ->once()
            ->withArgs(fn (BackupLog $arg, bool $verifyFirst): bool => $arg->is($log) && $verifyFirst === true)
            ->andReturnTrue();

        $this->artisanCommand('backup:restore', ['id' => $log->id, '--force' => true])
            ->expectsOutputToContain('Restore completed successfully')
            ->assertSuccessful();
    }

    #[Test]
    public function reports_failure_when_restore_service_returns_false(): void
    {
        $log = $this->createSuccessfulBackup();

        $this->mock(BackupService::class)
            ->shouldReceive('restoreBackup')
            ->once()
            ->andReturnFalse();

        $this->artisanCommand('backup:restore', ['id' => $log->id, '--force' => true])
            ->expectsOutputToContain('Restore failed')
            ->assertFailed();
    }

    #[Test]
    public function reports_failure_and_logs_when_restore_service_throws(): void
    {
        $log = $this->createSuccessfulBackup();

        $this->mock(BackupService::class)
            ->shouldReceive('restoreBackup')
            ->once()
            ->andThrow(new \RuntimeException('Unable to open backup archive'));

        $this->artisanCommand('backup:restore', ['id' => $log->id, '--force' => true])
            ->expectsOutputToContain('Unable to open backup archive')
            ->assertFailed();
    }

    #[Test]
    public function skip_verify_option_passes_verify_first_false_to_service(): void
    {
        $log = $this->createSuccessfulBackup();

        $this->mock(BackupService::class)
            ->shouldReceive('restoreBackup')
            ->once()
            ->withArgs(fn (BackupLog $arg, bool $verifyFirst): bool => $verifyFirst === false)
            ->andReturnTrue();

        $this->artisanCommand('backup:restore', ['id' => $log->id, '--force' => true, '--skip-verify' => true])
            ->assertSuccessful();
    }
}
