<?php

namespace Tests\Feature\Audit;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseCredentialsTest extends TestCase
{
    /**
     * Test that application uses non-root database credentials.
     */
    public function test_database_uses_least_privilege_user(): void
    {
        $envFile = base_path('.env');
        $this->assertFileExists($envFile, '.env file should exist');

        $content = file_get_contents($envFile);
        \preg_match('/^DB_USERNAME=(.+)$/m', $content, $userMatches);
        \preg_match('/^DB_PASSWORD=(.+)$/m', $content, $passMatches);

        $username = $userMatches[1] ?? null;
        $password = $passMatches[1] ?? null;

        $this->assertNotEmpty($username, 'DB_USERNAME should be set in .env');
        $this->assertNotEmpty($password, 'DB_PASSWORD should be set in .env');

        // Assert we are not using root
        $this->assertNotEquals('root', $username, 'Should not use root database user');

        // Assert username is the expected least-privilege user
        $this->assertSame('cems_app', $username, 'Database username should be cems_app');

        // Password should be strong (at least 12 chars, alphanumeric)
        $this->assertGreaterThanOrEqual(12, strlen($password), 'Password should be at least 12 characters');
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $password, 'Password should be alphanumeric');

        // If we are using mysql connection, verify config matches .env
        if (Config::get('database.default') === 'mysql') {
            $this->assertSame($username, Config::get('database.connections.mysql.username'));
            $this->assertSame($password, Config::get('database.connections.mysql.password'));
        }
    }

    /**
     * Test that database connection works with the configured credentials.
     */
    public function test_database_connection_is_established(): void
    {
        try {
            $pdo = DB::connection()->getPdo();
            $this->assertNotNull($pdo, 'Database connection should be established');
        } catch (\Exception $e) {
            $this->fail('Database connection failed: '.$e->getMessage());
        }
    }
}
