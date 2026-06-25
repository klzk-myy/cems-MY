<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

class AppKeyRotationTest extends TestCase
{
    /**
     * Test that APP_KEYs are different across environments.
     */
    public function test_app_keys_are_unique_across_environments(): void
    {
        $envFiles = [
            base_path('.env'),
            base_path('.env.backup'),
            base_path('.env.browser'),
        ];

        $keys = [];

        foreach ($envFiles as $file) {
            if (! file_exists($file)) {
                $this->markTestSkipped("Environment file not found: {$file}");
            }

            $content = file_get_contents($file);
            \preg_match('/^APP_KEY=(.+)$/m', $content, $matches);
            $this->assertNotEmpty($matches[1] ?? null, "APP_KEY not found in {$file}");
            $keys[$file] = trim($matches[1]);
        }

        // Assert all three keys are different
        $this->assertCount(3, array_unique($keys), 'APP_KEY values should be unique across environments');
    }

    /**
     * Test that Laravel config uses the correct key from .env.
     */
    public function test_laravel_uses_correct_app_key(): void
    {
        $envKey = config('app.key');
        $this->assertNotEmpty($envKey, 'App key should be configured');

        // Read from .env to verify it matches
        $envContent = file_get_contents(base_path('.env'));
        \preg_match('/^APP_KEY=(.+)$/m', $envContent, $matches);
        $envKeyValue = trim($matches[1] ?? '');

        $this->assertSame($envKey, $envKeyValue, 'Config key should match .env');
    }
}
