<?php

namespace Tests\Feature\Audit;

use App\Http\Middleware\EnsureSetupAccessible;
use App\Services\System\SetupService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EnsureSetupAccessibleTest extends TestCase
{
    public function test_middleware_denies_setup_routes_once_marker_exists(): void
    {
        $middleware = new EnsureSetupAccessible($this->completedSetupService());

        try {
            $middleware->handle(Request::create('/setup/wizard', 'GET'), static fn () => new Response('ok'));
            $this->fail('Expected HttpException to be thrown');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_middleware_keeps_reset_reachable_when_marker_exists(): void
    {
        $middleware = new EnsureSetupAccessible($this->completedSetupService());

        $route = new Route(['GET'], '/setup/reset', static fn () => 'ok');
        $route->name('setup.reset');

        $request = Request::create('/setup/reset', 'GET');
        $request->setRouteResolver(static fn () => $route);

        $response = $middleware->handle($request, static fn () => new Response('ok'));

        $this->assertSame('ok', $response->getContent());
    }

    public function test_middleware_passes_through_when_setup_incomplete(): void
    {
        $setupService = $this->createMock(SetupService::class);
        $setupService->method('isCompleted')->willReturn(false);

        $middleware = new EnsureSetupAccessible($setupService);

        $response = $middleware->handle(
            Request::create('/setup/wizard', 'GET'),
            static fn () => new Response('ok')
        );

        $this->assertSame('ok', $response->getContent());
    }

    private function completedSetupService(): SetupService
    {
        $setupService = $this->createMock(SetupService::class);
        $setupService->method('isCompleted')->willReturn(true);

        return $setupService;
    }
}
