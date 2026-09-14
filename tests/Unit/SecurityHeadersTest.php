<?php

namespace Tests\Unit;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    #[Test]
    public function csp_report_only_excludes_upgrade_insecure_requests(): void
    {
        app()->instance('env', 'local');
        $middleware = new SecurityHeaders;
        $request = Request::create('/test', 'GET');
        $response = new Response('test content');

        $result = $middleware->handle($request, function ($req) use ($response) {
            return $response;
        });

        $this->assertTrue($result->headers->has('Content-Security-Policy-Report-Only'));
        $csp = $result->headers->get('Content-Security-Policy-Report-Only');
        $this->assertStringNotContainsString('upgrade-insecure-requests', $csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
    }

    #[Test]
    public function csp_enforcement_includes_upgrade_insecure_requests(): void
    {
        app()->instance('env', 'production');
        $middleware = new SecurityHeaders;
        $request = Request::create('/test', 'GET');
        $response = new Response('test content');

        $result = $middleware->handle($request, function ($req) use ($response) {
            return $response;
        });

        $this->assertTrue($result->headers->has('Content-Security-Policy'));
        $csp = $result->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('upgrade-insecure-requests', $csp);
    }

    #[Test]
    public function script_src_drops_unsafe_inline_and_carries_nonce(): void
    {
        $middleware = new SecurityHeaders;
        $request = Request::create('/test', 'GET');
        $response = new Response('test content');

        $result = $middleware->handle($request, function ($req) use ($response) {
            return $response;
        });

        $csp = $result->headers->get('Content-Security-Policy')
            ?? $result->headers->get('Content-Security-Policy-Report-Only');

        preg_match('/script-src ([^;]+)/', $csp, $matches);

        $this->assertNotEmpty($matches, 'script-src directive missing');
        $scriptSrc = $matches[1] ?? '';
        $this->assertStringNotContainsString('unsafe-inline', $scriptSrc);
        $this->assertStringNotContainsString('unsafe-eval', $scriptSrc);
        $this->assertStringContainsString("'nonce-", $scriptSrc);
    }

    #[Test]
    public function middleware_applies_csp_headers(): void
    {
        $middleware = new SecurityHeaders;
        $request = Request::create('/test', 'GET');
        $response = new Response('test content');

        $result = $middleware->handle($request, function ($req) use ($response) {
            return $response;
        });

        $hasReportOnly = $result->headers->has('Content-Security-Policy-Report-Only');
        $hasStrict = $result->headers->has('Content-Security-Policy');

        $this->assertTrue($hasReportOnly || $hasStrict, 'CSP header should be set');
    }
}
