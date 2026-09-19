<?php

namespace Tests\Feature;

use App\Console\Commands\NormalizeColumnDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The db:normalize-column-defaults installer repairs quote-embedded and
 * literal-'NULL' string column defaults on MySQL/MariaDB. The ALTER work
 * is MySQL-only, so the feature surface here covers the graceful skip and
 * the defect classification that decides each repair.
 */
class NormalizeColumnDefaultsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_skips_gracefully_on_non_mysql_drivers(): void
    {
        $this->artisanCommand('db:normalize-column-defaults')->assertSuccessful();
    }

    #[Test]
    public function it_recognizes_only_suspect_information_schema_defaults(): void
    {
        $command = new class extends NormalizeColumnDefaults
        {
            public function suspect(string $raw): bool
            {
                return $this->isSuspect($raw);
            }
        };

        // MariaDB doubles a leading stored quote; the NULL-text defect
        // shows as NULL or 'NULL'. Healthy defaults never reach SHOW COLUMNS.
        $this->assertTrue($command->suspect("''flag''"));
        $this->assertTrue($command->suspect('NULL'));
        $this->assertTrue($command->suspect("'NULL'"));
        $this->assertFalse($command->suspect("'pending'"));
        $this->assertFalse($command->suspect('pending'));
        $this->assertFalse($command->suspect(''));
    }

    #[Test]
    public function it_classifies_corrupt_and_healthy_defaults(): void
    {
        $command = new class extends NormalizeColumnDefaults
        {
            /**
             * @return array{action: string, value: ?string, label: string}|null
             */
            public function classify(?string $stored, bool $nullable): ?array
            {
                return $this->classifyStoredDefault($stored, $nullable);
            }
        };

        // Healthy defaults are untouched.
        $this->assertNull($command->classify('pending', true));
        $this->assertNull($command->classify(null, true));
        $this->assertNull($command->classify('', false));

        // Quote-embedded default → repair to the unwrapped value.
        $decision = $command->classify("'flag'", false);
        $this->assertSame('set', $decision['action']);
        $this->assertSame('flag', $decision['value']);

        // Literal NULL text on a nullable column → drop to a real NULL.
        $this->assertSame('null', $command->classify('NULL', true)['action']);
        $this->assertSame('null', $command->classify('null', true)['action']);
        $this->assertSame('null', $command->classify("'NULL'", true)['action']);

        // Literal NULL text on a NOT NULL column → warn, never auto-fix.
        $this->assertSame('warn', $command->classify('NULL', false)['action']);
        $this->assertSame('warn', $command->classify("'NULL'", false)['action']);
    }
}
