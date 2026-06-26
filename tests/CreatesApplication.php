<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        // PHP's variables_order=GPCS on this system (missing 'E'), so $_ENV is never
        // populated from environment variables. Laravel's Env::get() checks $_ENV first:
        //   $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?? $default
        // When $_ENV is empty, Env::get() relies on $_SERVER or getenv(). PHPUnit sets
        // env vars via putenv() (updates getenv), but the CLI 'APP_ENV=testing' prefix
        // is the only way $_SERVER gets it. If running without the CLI prefix (e.g.
        // via an IDE or CI), $_SERVER won't have APP_ENV either.
        //
        // Set APP_ENV=testing across all three sources before bootstrap/app.php is
        // loaded so Dotenv loads .env.testing and all config is built for testing.
        //
        // Also force REDIS_PASSWORD to empty so config/database.php loads with
        // password=null (env('REDIS_PASSWORD') ?: null), preventing Redis AUTH errors
        // on job dispatch (Horizon's StoreJob listener connects to Redis).
        if (getenv('APP_ENV') === 'testing' || ($_SERVER['APP_ENV'] ?? null) === 'testing') {
            putenv('APP_ENV=testing');
            $_ENV['APP_ENV'] = 'testing';
            $_SERVER['APP_ENV'] = 'testing';

            putenv('REDIS_PASSWORD=');
            $_ENV['REDIS_PASSWORD'] = '';
            $_SERVER['REDIS_PASSWORD'] = '';
        }

        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
