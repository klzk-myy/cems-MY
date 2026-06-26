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
        if (Config::get('database.default') !== 'mysql') {
            $this->markTestSkipped('Only applies to mysql database connection');
        }

        $username = Config::get('database.connections.mysql.username');
        $password = Config::get('database.connections.mysql.password');

        $this->assertNotEmpty($username, 'DB_USERNAME should be configured');
        $this->assertNotEmpty($password, 'DB_PASSWORD should be configured');

        // Assert we are not using root
        $this->assertNotEquals('root', $username, 'Should not use root database user');

        // Password should be strong (at least 12 chars, alphanumeric)
        $this->assertGreaterThanOrEqual(12, strlen($password), 'Password should be at least 12 characters');
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9@#%&*!_-]+$/', $password, 'Password should not contain spaces');
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
