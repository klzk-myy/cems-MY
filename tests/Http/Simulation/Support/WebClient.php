<?php

namespace Tests\Http\Simulation\Support;

/**
 * WebClient — session + CSRF driven black-box client for the web surface.
 *
 * Stateless thin wrapper. CSRF token state lives in SimulationTestCase;
 * cookie handling lives in SimulationTestCase's webRequester. This class
 * just forwards method calls to the requester closure.
 */
class WebClient
{
    public const BASE = '/';

    /** @var callable(string, string, array<string, mixed>): array<string, mixed>|null */
    private $requester = null;

    /**
     * Username of the currently logged-in web identity, tracked by the client
     * so SimulationTestCase::asWebUser() can skip redundant logout/login
     * round-trips (the login route is throttled at 5 attempts/minute).
     */
    public ?string $currentUser = null;

    public function withRequester(callable $requester): self
    {
        $this->requester = $requester;

        return $this;
    }

    public function login(string $email, string $password): void
    {
        $this->fetch('GET', '/login');
        $resp = $this->fetch('POST', '/login', [
            'username' => $email,
            'password' => $password,
            // The login throttle buckets by IP+email; the harness drives many
            // sequential logins (teller/manager/manager2/admin), so scoping
            // the bucket per role keeps the 5/minute production limit intact
            // while allowing the full business-day sweep to authenticate.
            'email' => $email.'@cems.my',
        ]);

        // Fail loudly here rather than letting a throttled/broken login
        // surface as a confusing 302/403 several steps later.
        if (($resp['status'] ?? 0) !== 302) {
            throw new \RuntimeException(
                "WebClient login for [{$email}] did not redirect (got "
                .($resp['status'] ?? 'null').'): '.substr((string) ($resp['body'] ?? ''), 0, 200)
            );
        }

        $this->currentUser = $email;
    }

    public function logout(): void
    {
        $this->fetch('POST', '/logout', []);
        $this->currentUser = null;
    }

    public function get(string $path): array
    {
        return $this->fetch('GET', $path);
    }

    public function post(string $path, array $payload = []): array
    {
        return $this->fetch('POST', $path, $payload);
    }

    public function put(string $path, array $payload = []): array
    {
        return $this->fetch('PUT', $path, $payload);
    }

    /** @return array<string, mixed> */
    private function fetch(string $method, string $path, array $payload = []): array
    {
        if ($this->requester === null) {
            return [
                'status' => 500,
                'body' => 'WebClient has no requester — call withRequester() first',
                'headers' => [],
            ];
        }

        return call_user_func($this->requester, $method, $path, $payload);
    }
}
