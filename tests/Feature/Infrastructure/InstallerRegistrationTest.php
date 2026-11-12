<?php

namespace Tests\Feature\Infrastructure;

use App\Console\Commands\InstallAuditFks;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Guards the installer registration failure mode that has already caused
 * production outages: an `Install*` command whose schema change never reaches
 * a live database because nobody added it to the deploy workflow.
 *
 * The command list in .github/workflows/deploy.yml is the ONLY enforcement
 * that these upgrade commands run in production, so every installer must be
 * named there.
 */
class InstallerRegistrationTest extends TestCase
{
    #[Test]
    public function every_installer_command_is_registered_in_the_deploy_workflow(): void
    {
        $deploy = File::get(base_path('.github/workflows/deploy.yml'));

        $installers = collect(glob(app_path('Console/Commands/Install*.php')) ?: [])
            ->map(fn (string $path) => 'App\\Console\\Commands\\'.basename($path, '.php'))
            ->values();

        $this->assertNotEmpty($installers, 'Expected at least one Install* command.');

        foreach ($installers as $class) {
            /** @var InstallAuditFks $command */
            $command = app($class);
            $name = $command->getName();

            $this->assertStringContainsString(
                $name,
                $deploy,
                "Installer [{$name}] ({$class}) is not registered in .github/workflows/deploy.yml "
                .'— it will never reach production.'
            );
        }
    }
}
