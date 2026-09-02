<?php

namespace Tests\Http\Simulation\Support;

use Tests\TestCase;

/**
 * SimulationTestCase — base class for every simulation wave test.
 *
 * The harness is a PHPUnit Feature suite, so it drives the application HTTP
 * kernel in-process rather than against a separate `php artisan serve`
 * instance. This class provides the two requester closures the clients
 * expect:
 *
 *  - webRequester() — routes through the web middleware stack (session cookie
 *    jar + CSRF token, just like a browser hitting the app).
 *  - apiRequester($token) — routes through the api middleware stack with a
 *    Sanctum Bearer token.
 *
 * Every workflow step in a wave goes through one of these two closures, so
 * the harness never calls a service, model, DB::, or tinker directly.
 */
abstract class SimulationTestCase extends TestCase
{
    /** @var array<string, string> */
    private array $webCookies = [];

    private ?string $webCsrf = null;

    /**
     * Drive a request through the web middleware stack.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function webRequester(string $method, string $path, array $payload = []): array
    {
        $method = strtoupper($method);

        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) && $this->webCsrf === null) {
            $this->refreshWebCsrf();
        }

        $headers = [];
        $body = [];

        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) && $this->webCsrf !== null) {
            $headers['X-CSRF-Token'] = $this->webCsrf;
            $body['_token'] = $this->webCsrf;
        }

        $body = array_merge($body, $payload);

        $response = $this->dispatchWeb($method, $path, $body, $headers);

        $this->captureWebCookies($response);

        return [
            'status' => $response->status(),
            'body' => $response->getContent(),
            'headers' => $response->headers->all(),
        ];
    }

    /**
     * Drive a request through the api middleware stack with a Sanctum token.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function apiRequester(string $token, string $method, string $path, array $payload = []): array
    {
        $method = strtoupper($method);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->{$method}(self::apiPath($path), $payload);

        return [
            'status' => $response->status(),
            'body' => $response->getContent(),
            'headers' => $response->headers->all(),
        ];
    }

    /**
     * Build a WebClient / ApiClient wired to the in-process requesters.
     */
    protected function newWebClient(): WebClient
    {
        return (new WebClient)->withRequester(function (string $method, string $path, array $payload): array {
            return $this->webRequester($method, $path, $payload);
        });
    }

    protected function newApiClient(string $token): ApiClient
    {
        return (new ApiClient)->withRequester(function (string $method, string $path, array $payload) use ($token): array {
            return $this->apiRequester($token, $method, $path, $payload);
        });
    }

    /**
     * Refresh the CSRF token by GETting the login page.
     */
    private function refreshWebCsrf(): void
    {
        $response = $this->withCookies($this->webCookies)->get('/login');
        $this->captureWebCookies($response);
        $body = $response->getContent();

        if (is_string($body) && preg_match('/name="_token"\s+value="([^"]+)"/', $body, $m)) {
            $this->webCsrf = $m[1];
        } elseif (is_string($body) && preg_match('/<meta\s+name="csrf-token"\s+content="([^"]+)"/', $body, $m)) {
            $this->webCsrf = $m[1];
        } elseif (is_string($body) && preg_match('/<meta\s+content="([^"]+)"\s+name="csrf-token"/', $body, $m)) {
            $this->webCsrf = $m[1];
        }
    }

    /**
     * Capture Set-Cookie headers so the next request carries the session.
     *
     * @param  mixed  $response  A Laravel response with ->headers
     */
    private function captureWebCookies($response): void
    {
        $cookies = $response->headers->get('Set-Cookie');
        if (! $cookies) {
            return;
        }

        foreach (is_array($cookies) ? $cookies : [$cookies] as $cookie) {
            if (is_string($cookie) && preg_match('/^([^=]+)=([^;]+)/', $cookie, $m)) {
                $this->webCookies[$m[1]] = $m[2];
            }
        }
    }

    /**
     * Dispatch a single web request, applying the cookie jar and headers.
     *
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $headers
     */
    private function dispatchWeb(string $method, string $path, array $body, array $headers): mixed
    {
        $pending = $this->withCookies($this->webCookies)->withHeaders($headers);

        return match ($method) {
            'GET' => $pending->get($path),
            'POST' => $pending->post($path, $body),
            'PUT' => $pending->put($path, $body),
            'PATCH' => $pending->patch($path, $body),
            'DELETE' => $pending->delete($path, $body),
            default => $pending->get($path),
        };
    }

    private static function apiPath(string $path): string
    {
        return str_starts_with($path, '/api/v1') ? $path : '/api/v1'.$path;
    }
}
