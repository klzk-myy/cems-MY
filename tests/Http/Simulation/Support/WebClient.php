<?php

namespace Tests\Http\Simulation\Support;

/**
 * WebClient — session + CSRF driven black-box client for the web surface.
 *
 * Sends `_token` + `X-CSRF-Token` on every state-changing request, exactly
 * like a browser would after loading the login page. Auth is via POST
 * /login (username + password).
 *
 * The harness is a PHPUnit Feature suite, so it drives the application HTTP
 * kernel in-process rather than against a separate `php artisan serve`
 * instance. The requester closure is provided by the test class and routes
 * the request through the web middleware stack directly. Cookie handling
 * (session + CSRF) lives in the requester, since the test class owns the
 * cookie jar and the in-process request builder.
 */
class WebClient
{
    public const BASE = '/';

    private ?string $csrf = null;

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
     * Authenticate via the web login form.
     */
    public function login(string $email, string $password): void
    {
        $this->refreshCsrf();

        $response = $this->fetch('POST', '/login', [
            'username' => $email,
            'password' => $password,
        ]);

        if ($response['status'] >= 300 && $response['status'] < 400) {
            $location = $response['headers']['location'][0] ?? '/';
            $this->fetch('GET', $location);
        }
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
     * POST to a path.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function post(string $path, array $payload = []): array
    {
        return $this->fetch('POST', $path, $payload);
    }

    /**
     * PUT to a path.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function put(string $path, array $payload = []): array
    {
        return $this->fetch('PUT', $path, $payload);
    }

    /**
     * Refresh the CSRF token by scraping it out of the login page HTML.
     */
    public function refreshCsrf(): void
    {
        $response = $this->fetch('GET', '/login');
        $body = $response['body'];

        if (preg_match('/name="_token"\s+value="([^"]+)"/', $body, $m)) {
            $this->csrf = $m[1];
        } elseif (preg_match('/<meta\s+name="csrf-token"\s+content="([^"]+)"/', $body, $m)) {
            $this->csrf = $m[1];
        } elseif (preg_match('/<meta\s+content="([^"]+)"\s+name="csrf-token"/', $body, $m)) {
            $this->csrf = $m[1];
        }
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
                'body' => 'WebClient has no requester — call withRequester() first',
                'headers' => [],
            ];
        }

        $method = strtoupper($method);

        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) && $this->csrf !== null) {
            $payload['_token'] = $this->csrf;
        }

        return call_user_func($this->requester, $method, $path, $payload);
    }
}
