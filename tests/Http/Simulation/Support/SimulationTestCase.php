<?php

namespace Tests\Http\Simulation\Support;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
 * the harness never calls a service, model, DB::, or tinker directly. The
 * only exceptions are test bootstrap (setUp seeding, token minting, and the
 * cross-test truncate list in afterRefreshingDatabase) — no step body reads
 * or writes state except over HTTP.
 *
 * Tokens are minted in-process by setUp() (mintTokens), so the harness is
 * self-contained and also runs standalone via `php artisan test`.
 */
abstract class SimulationTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * Session cookie jar for the web requester. Protected (not private) so
     * Wave B attack steps can inject forged cookies.
     *
     * @var array<string, string>
     */
    protected array $webCookies = [];

    private ?string $webCsrf = null;

    /** @var array<string, string> */
    private static array $tokens = [];

    /** WebClient wired to the in-process web requester. */
    protected WebClient $webClient;

    /** Per-scenario references shared across wave steps. */
    protected SimulationState $state;

    /**
     * Build the schema via SchemaSeeder instead of migrate:fresh.
     *
     * database/migrations/ is retired; SchemaSeeder is the single source of
     * truth for the 85-table schema. RefreshDatabase would otherwise call
     * migrate:fresh and fail with no migrations present.
     */
    protected function migrateDatabases()
    {
        $this->artisan('db:seed', ['--class' => 'Database\Seeders\SchemaSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\Seeders\FiscalYearSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\Seeders\AccountingPeriodSeeder']);
    }

    /**
     * Seed the simulation identity graph after every refresh.
     *
     * RefreshDatabase runs migrateDatabases() once (cached in
     * RefreshDatabaseState), but afterRefreshingDatabase() runs every test,
     * so the graph is always present when a wave step reads it.
     */
    protected function afterRefreshingDatabase()
    {
        // The in-memory test DB is shared across tests in the process, and
        // RefreshDatabase only re-migrates once — so state from a previous
        // test (e.g. an opened counter session) leaks into the next. Truncate
        // the tables a wave mutates so every test starts from the seeded
        // graph, not from a previous test's leftovers.
        $tables = [
            'counter_sessions', 'till_balances', 'teller_allocations',
            'currency_positions', 'transactions', 'customers',
            'stock_transfers', 'stock_transfer_items', 'journal_entries',
            'journal_lines', 'alerts', 'compliance_cases',
            'compliance_case_notes', 'compliance_case_links',
            'compliance_findings', 'flagged_transactions',
            'enhanced_diligence_records', 'edd_questionnaire_responses',
            'bank_reconciliations', 'bank_reconciliation_items',
            'budgets', 'budget_actuals', 'revaluation_entries',
            'account_ledgers', 'audit_logs', 'system_logs',
            'notifications', 'user_notification_preferences',
            'sessions',
        ];
        $schema = DB::getSchemaBuilder();
        foreach ($tables as $table) {
            if ($schema->hasTable($table)) {
                DB::table($table)->delete();
            }
        }

        $this->artisan('db:seed', ['--class' => 'Database\Seeders\SimulationSeeder']);
    }

    /**
     * Mint Sanctum tokens for the four simulation roles in the test DB.
     *
     * Tokens are stored in self::$tokens so wave steps can authenticate via
     * the apiRequester closure.
     */
    private function mintTokens(): void
    {
        self::$tokens = [];
        foreach (['teller' => 'sim_teller', 'manager' => 'sim_manager', 'compliance' => 'sim_compliance', 'admin' => 'sim_admin'] as $role => $username) {
            $user = User::where('username', $username)->first();
            if (! $user) {
                continue;
            }
            $user->tokens()->delete();
            $token = $user->createToken('simulation')->plainTextToken;
            self::$tokens[$role] = $token;
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        // RefreshDatabase seeds the graph in afterRefreshingDatabase(), which
        // runs during setUpTraits() — before this point. Tests without a DB
        // trait (e.g. OracleSmokeTest) never run it, so seed here as a
        // fallback if the graph is missing.
        if (User::count() === 0) {
            $this->artisan('db:seed', ['--class' => 'Database\Seeders\SimulationSeeder']);
        }

        $this->mintTokens();

        $this->state = $this->buildState();
        $this->webClient = $this->newWebClient();
    }

    /**
     * Build the state graph and web client after the DB trait has booted.
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

        // Isolate API requests from web authentication state. Two leaks must
        // be stopped:
        //
        // 1. Cookie leak — MakesHttpRequests::withCookies() merges into
        //    $this->defaultCookies permanently, so web login cookies (the
        //    session cookie) leak onto every subsequent request. Clear
        //    defaultCookies for the API request, then restore.
        //
        // 2. Guard/session leak — Sanctum's Guard resolves the web-guard user
        //    BEFORE the Bearer token. forgetGuards() clears resolved guard
        //    instances, but the SessionGuard re-loads its user from the session
        //    store on next access. Flushing the loaded attributes below
        //    removes the web-login key from the in-memory store (the web
        //    session's persisted record survives on the array driver).
        $savedCookies = $this->defaultCookies ?? [];
        $savedHeaders = $this->defaultHeaders ?? [];
        $this->defaultCookies = [];

        // The session store is a singleton across in-process requests and
        // keeps the web session's id. The API request does not send the web
        // session cookie, so Sanctum resolves no web user; flushing the
        // loaded attributes is belt-and-braces so Guard cannot resolve the
        // web-login user ahead of the Bearer token. The array session driver
        // keys persisted data by session id, so the web session's record
        // survives our in-memory flush.
        $session = $this->app['session.store'];
        $savedSessionAttributes = $session->all();
        $session->flush();
        $this->app['auth']->forgetGuards();

        try {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/json',
            ])->{$method}(self::apiPath($path), $payload);
        } finally {
            // Headers merge into defaultHeaders permanently (same leak class
            // as cookies), so a Bearer token would otherwise bleed into later
            // web requests. The API middleware also flips the default guard
            // to sanctum via shouldUse() and it is never reset in-process,
            // so web controllers would resolve auth() via Sanctum. Restore
            // both so the next web request is session-authenticated again.
            // The API request ended with its own random session id; its empty
            // attributes are flushed and the web session's attributes restored
            // so the next web request re-loads them from the handler.
            $session->flush();
            $session->replace($savedSessionAttributes);
            $this->defaultHeaders = $savedHeaders;
            $this->app['auth']->shouldUse('web');
            $this->app['auth']->forgetGuards();
            $this->defaultCookies = $savedCookies;
        }

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
        return (new WebClient)
            ->withRequester(function (string $method, string $path, array $payload): array {
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
     * Run a step under a different web identity, then restore a named web
     * session. Steps must not leak role changes implicitly, which is why the
     * switch-and-restore is centralized here.
     *
     * The Wave A sweep deliberately progresses: teller (A1–A4) → branch
     * manager (A5–A9, approval/cancellation/EOD surfaces require it) → admin
     * (A10 onward, stock transfers + system surfaces are admin-only). Each
     * transition passes `$restore` explicitly so the intended identity is
     * visible at the call site.
     *
     * @param  callable(): void  $step
     * @param  string  $restore  Username to log back in as afterwards
     */
    protected function asWebUser(string $username, callable $step, string $restore = 'sim_teller'): void
    {
        // Skip redundant logout/login round-trips: the login route is
        // throttled at 5 attempts/minute, and a 32-step business-day sweep
        // otherwise exhausts the throttle with no-op identity switches.
        if ($this->webClient->currentUser === $username) {
            $step();

            if ($restore !== $username) {
                $this->webClient->logout();
                $this->webClient->login($restore, 'Test@1234');
            }

            return;
        }

        $this->webClient->logout();
        $this->webClient->login($username, 'Test@1234');

        try {
            $step();
        } finally {
            $this->webClient->logout();
            $this->webClient->login($restore, 'Test@1234');
        }
    }

    /**
     * Fetch a token minted by simulation:run for a seeded role.
     */
    protected function tokenFor(string $role): string
    {
        $token = self::$tokens[$role] ?? null;

        if (! is_string($token) || $token === '') {
            $this->fail("No token available for role [{$role}].");
        }

        return $token;
    }

    /**
     * Which surface the harness should drive (web, api, or both), set by
     * `simulation:run --surface=...` via the SIM_SURFACE env var. Defaults
     * to `both` when the suite runs standalone via phpunit/artisan test.
     */
    protected function surface(): string
    {
        $surface = getenv('SIM_SURFACE');

        return is_string($surface) && in_array($surface, ['web', 'api', 'both'], true)
            ? $surface
            : 'both';
    }

    /**
     * Whether assertions for the given surface should execute this run.
     */
    protected function surfaceAllows(string $surface): bool
    {
        return $this->surface() === 'both' || $this->surface() === $surface;
    }

    /**
     * Run a step only when the given surface is selected. Used to gate the
     * web/API halves of paired wave steps; single-surface steps (routes that
     * exist on one surface only) always run.
     */
    protected function runOnSurface(string $surface, callable $step): void
    {
        if (! $this->surfaceAllows($surface)) {
            return;
        }

        $step();
    }

    /**
     * Assert a client response status, surfacing the body on failure.
     */
    protected function assertSurfaceStatus(array $response, int $expected, string $label): void
    {
        $actual = $response['status'] ?? null;
        $this->assertSame(
            $expected,
            $actual,
            "{$label}: expected HTTP {$expected}, got {$actual}. Body: ".($response['body'] ?? '')
        );
    }

    /**
     * Decode an API response body and unwrap the `data` envelope.
     *
     * resourceResponse() wraps resources via `->additional()`, so payloads
     * arrive as {success, message, data: {...}}. This helper unwraps that so
     * steps can assert on the resource directly.
     *
     * @return array<string, mixed>
     */
    protected function apiData(array $response): array
    {
        $body = json_decode($response['body'], true);
        if (! is_array($body)) {
            return [];
        }

        return is_array($body['data'] ?? null) ? $body['data'] : $body;
    }

    /**
     * The seeded counter's `code` (its route key on the web surface).
     */
    protected function counterCode(): string
    {
        $code = $this->state->oracle->scalar(
            'SELECT code FROM counters WHERE id = ?', [$this->state->counterId]
        );

        return is_string($code) ? $code : 'C001';
    }

    /**
     * Refresh the CSRF token by scraping it out of a page that always
     * renders the token. Uses /login (works for guests) — the caller is
     * responsible for hitting a different page if the user is already
     * authenticated.
     */
    private function refreshWebCsrf(): void
    {
        $response = $this->withCookies($this->webCookies)->get('/login');
        $this->captureWebCookies($response);
        $this->scrapeCsrfToken((string) $response->getContent());
    }

    /**
     * Scrape the CSRF token out of a rendered page, tolerating the three
     * markup variants the app uses.
     */
    private function scrapeCsrfToken(string $body): void
    {
        if (preg_match('/name="_token"\s+value="([^"]+)"/', $body, $m)) {
            $this->webCsrf = $m[1];
        } elseif (preg_match('/<meta\s+name="csrf-token"\s+content="([^"]+)"/', $body, $m)) {
            $this->webCsrf = $m[1];
        } elseif (preg_match('/<meta\s+content="([^"]+)"\s+name="csrf-token"/', $body, $m)) {
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
        // HeaderBag::get('Set-Cookie') returns only the first value, so use
        // all() to read every Set-Cookie header on the response.
        $cookies = $response->headers->all('Set-Cookie');
        if (! $cookies) {
            return;
        }

        foreach ($cookies as $cookie) {
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
    protected function dispatchWeb(string $method, string $path, array $body, array $headers): mixed
    {
        $pending = $this->withCookies($this->webCookies)->withHeaders($headers);

        $response = match ($method) {
            'GET' => $pending->get($path),
            'POST' => $pending->post($path, $body),
            'PUT' => $pending->put($path, $body),
            'PATCH' => $pending->patch($path, $body),
            'DELETE' => $pending->delete($path, $body),
            default => $pending->get($path),
        };

        return $response;
    }

    /**
     * Build the per-scenario identity graph from the seeded simulation DB.
     */
    private function buildState(): SimulationState
    {
        $state = new SimulationState;
        $state->oracle = new SimulationOracle(DB::connection());

        $state->branchId = (int) $state->oracle->scalar(
            "SELECT id FROM branches WHERE code = 'HQ' LIMIT 1"
        );
        $state->counterId = (int) $state->oracle->scalar(
            "SELECT id FROM counters WHERE code = 'C001' LIMIT 1"
        );
        $state->tellerId = (int) $state->oracle->scalar(
            "SELECT id FROM users WHERE username = 'sim_teller' LIMIT 1"
        );
        $state->managerId = (int) $state->oracle->scalar(
            "SELECT id FROM users WHERE username = 'sim_manager' LIMIT 1"
        );
        $state->complianceId = (int) $state->oracle->scalar(
            "SELECT id FROM users WHERE username = 'sim_compliance' LIMIT 1"
        );
        $state->adminId = (int) $state->oracle->scalar(
            "SELECT id FROM users WHERE username = 'sim_admin' LIMIT 1"
        );
        $state->customerId = (int) $state->oracle->scalar(
            "SELECT id FROM customers WHERE email = 'sim_customer@cems.my' LIMIT 1"
        );
        $state->tellerToken = $this->tokenFor('teller');

        return $state;
    }

    private static function apiPath(string $path): string
    {
        return str_starts_with($path, '/api/v1') ? $path : '/api/v1'.$path;
    }
}
