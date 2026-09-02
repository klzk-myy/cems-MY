<?php

namespace Tests\Http\Simulation\Support;

/**
 * ApiClient — Sanctum token driven black-box client for the API v1 surface.
 *
 * Sends `Authorization: Bearer {token}` and `Accept: application/json` on every
 * request. Stateless token auth — no CSRF, no cookie jar.
 *
 * The harness is a PHPUnit Feature suite, so it drives the application HTTP
 * kernel in-process rather than against a separate `php artisan serve`
 * instance. The requester closure is provided by the test class and routes
 * the request through the api middleware stack directly. The token travels
 * inside the closure rather than on the client, so the client stays stateless.
 */
class ApiClient
{
    public const BASE = '/api/v1';

    /** @var callable(string, string, array<string, mixed>): array<string, mixed>|null */
    private $requester = null;

    /**
     * Inject the in-process requester used to actually dispatch requests.
     *
     * @param  callable(string $method, string $path, array<string, mixed> $payload): array<string, mixed>  $requester
     */
    public function withRequester(callable $requester): self
    {
        $this->requester = $requester;

        return $this;
    }

    /**
     * GET a path.
     *
     * @return array<string, mixed>
     */
    public function get(string $path): array
    {
        return $this->fetch('GET', $path);
    }

    /**
     * POST a JSON payload to a path.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function post(string $path, array $payload = []): array
    {
        return $this->fetch('POST', $path, $payload);
    }

    /**
     * Perform a single HTTP request through the injected requester.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function fetch(string $method, string $path, array $payload = []): array
    {
        if ($this->requester === null) {
            return [
                'status' => 500,
                'body' => 'ApiClient has no requester — call withRequester() first',
                'headers' => [],
            ];
        }

        return call_user_func($this->requester, $method, $path, $payload);
    }
}
