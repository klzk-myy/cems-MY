<?php

namespace Tests\Unit;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    /**
     * Test that in non-production environments, the CSP Report-Only header
     * does NOT include the invalid 'upgrade-insecure-requests' directive.
     */
    public function test_csp_report_only_excludes_upgrade_insecure_requests(): void
    {
        // Test directly with local environment mock
        $middleware = new SecurityHeaders;
        $request = Request::create('/test', 'GET');
        $response = new Response('test content');

        // Manually test the buildCsp method behavior
        $reflection = new \ReflectionClass($middleware);
        $method = $reflection->getMethod('buildCsp');
        $method->setAccessible(true);

        // When reportOnly is true, upgrade-insecure-requests should be excluded
        $cspReportOnly = $method->invoke($middleware, true);
        $this->assertStringNotContainsString('upgrade-insecure-requests', $cspReportOnly);

        // Verify the header contains other directives
        $this->assertStringContainsString("default-src 'self'", $cspReportOnly);
    }

    /**
     * Test that in production, the strict CSP header includes
     * 'upgrade-insecure-requests' (valid in enforcement mode).
     */
    public function test_csp_enforcement_includes_upgrade_insecure_requests(): void
    {
        $middleware = new SecurityHeaders;
        $request = Request::create('/test', 'GET');
        $response = new Response('test content');

        // Manually test the buildCsp method behavior
        $reflection = new \ReflectionClass($middleware);
        $method = $reflection->getMethod('buildCsp');
        $method->setAccessible(true);

        // When reportOnly is false, upgrade-insecure-requests should be included
        $csp = $method->invoke($middleware, false);
        $this->assertStringContainsString('upgrade-insecure-requests', $csp);
    }

    /**
     * Test the full middleware behavior with environment detection.
     */
    public function test_middleware_applies_csp_headers(): void
    {
        $middleware = new SecurityHeaders;
        $request = Request::create('/test', 'GET');
        $response = new Response('test content');

        $result = $middleware->handle($request, function ($req) use ($response) {
            return $response;
        });

        // Should have CSP headers applied (either Report-Only or strict depending on env)
        $hasReportOnly = $result->headers->has('Content-Security-Policy-Report-Only');
        $hasStrict = $result->headers->has('Content-Security-Policy');

        $this->assertTrue($hasReportOnly || $hasStrict, 'CSP header should be set');
    }
}
