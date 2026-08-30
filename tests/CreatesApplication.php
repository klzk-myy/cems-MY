<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

trait CreatesApplication
{
    /**
     * Variables the deployment shell exports for the LIVE staging app.
     *
     * PHP runs with variables_order=GPCS here (no 'E'), so $_ENV is never
     * populated from the process environment at startup, but $_SERVER is.
     * Laravel's env() helper reads $_SERVER before $_ENV, and neither
     * PHPUnit's <env> handler (which rebuilds $_SERVER as
     * array_merge($phpunitEnv, $_SERVER)) nor Dotenv's safeLoad() will
     * overwrite a value that is already present. So the shell's staging
     * values silently won over phpunit.xml and .env.testing:
     *
     *   - DB_CONNECTION=mysql / DB_DATABASE=cems_my_staging meant the whole
     *     suite ran against the live staging database and RefreshDatabase's
     *     migrate:fresh dropped its tables.
     *   - APP_KEY was the real staging key, so tests encrypted/decrypted with
     *     production credentials.
     *   - CACHE_DRIVER/QUEUE_CONNECTION=redis and SESSION_DRIVER=file pulled
     *     tests onto Redis and the file session store.
     *
     * phpunit.xml force="true" puts the intended test values into putenv() and
     * $_ENV. Unsetting these keys from $_SERVER lets $_ENV win, making the
     * test environment hermetic regardless of shell pollution.
     */
    private const DEPLOYMENT_ENV_VARS = [
        'APP_ENV',
        'APP_KEY',
        'BCRYPT_ROUNDS',
        'CACHE_DRIVER',
        'RATE_LIMIT_CACHE_STORE',
        'DB_CONNECTION',
        'DB_DATABASE',
        'DB_HOST',
        'DB_PORT',
        'DB_USERNAME',
        'DB_PASSWORD',
        'DB_SOCKET',
        'DB_LOGGING',
        'MAIL_MAILER',
        'PULSE_ENABLED',
        'QUEUE_CONNECTION',
        'SESSION_DRIVER',
        'REDIS_CLIENT',
        'REDIS_HOST',
        'REDIS_PASSWORD',
        'BACKUP_NOTIFY_EMAIL',
        'TELESCOPE_ENABLED',
        'ALLOW_DERIVED_ENCRYPTION_SALT',
        'SECURITY_IP_BLOCKING_ENABLED',
        'XDEBUG_MODE',
    ];

    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        if ($this->isTestRun()) {
            foreach (self::DEPLOYMENT_ENV_VARS as $key) {
                unset($_SERVER[$key]);
            }

            putenv('APP_ENV=testing');
            $_ENV['APP_ENV'] = 'testing';
            $_SERVER['APP_ENV'] = 'testing';
        }

        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    /**
     * Detects whether phpunit.xml has been applied to this process.
     */
    private function isTestRun(): bool
    {
        return getenv('APP_ENV') === 'testing'
            || ($_SERVER['APP_ENV'] ?? null) === 'testing';
    }
}
