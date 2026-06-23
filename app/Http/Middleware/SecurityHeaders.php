<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Security headers configuration for CEMS-MY.
     *
     * Implements BNM compliance requirements and OWASP security best practices.
     *
     * @var array<string, string|array>
     */
    protected array $securityHeaders = [
        // Prevent browsers from MIME-sniffing responses
        'X-Content-Type-Options' => 'nosniff',

        // Prevent clickjacking
        'X-Frame-Options' => 'DENY',

        // XSS Protection (legacy, but still useful for older browsers)
        'X-XSS-Protection' => '1; mode=block',

        // Referrer Policy
        'Referrer-Policy' => 'strict-origin-when-cross-origin',

        // Permissions Policy (formerly Feature-Policy)
        'Permissions-Policy' => 'geolocation=(), microphone=(), camera=(), payment=(), usb=(), magnetometer=(), gyroscope=(), speaker=(), vibrate=(), fullscreen=(self)',

        // Cache control for sensitive pages
        'Cache-Control' => 'no-store, no-cache, must-revalidate, proxy-revalidate',

        // Pragma (legacy HTTP/1.0)
        'Pragma' => 'no-cache',

        // Expires (legacy)
        'Expires' => '0',
    ];

    /**
     * Content Security Policy directives.
     *
     * Configured for Tailwind CSS + Alpine.js compatibility.
     * Note: 'unsafe-inline' is required for Tailwind CSS to function properly.
     * 'unsafe-eval' is disabled for security.
     *
     * @var array<string, string>
     */
    protected array $cspDirectives = [
        'default-src' => "'self'",
        'script-src' => "'self' 'unsafe-inline'",
        'style-src' => "'self' 'unsafe-inline'",
        'img-src' => "'self' data: https:",
        'font-src' => "'self' data:",
        'connect-src' => "'self'",
        'media-src' => "'self'",
        'object-src' => "'none'",
        'frame-ancestors' => "'none'",
        'form-action' => "'self'",
        'base-uri' => "'self'",
        'upgrade-insecure-requests' => '',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Apply security headers
        $this->applySecurityHeaders($response);

        // Apply Content Security Policy
        $this->applyCsp($response);

        // Apply HSTS in production
        $this->applyHsts($response);

        // Remove sensitive headers
        $this->removeSensitiveHeaders($response);

        return $response;
    }

    /**
     * Apply standard security headers.
     */
    protected function applySecurityHeaders(Response $response): void
    {
        foreach ($this->securityHeaders as $header => $value) {
            if (! $response->headers->has($header)) {
                $response->headers->set($header, $value);
            }
        }
    }

    /**
     * Apply Content Security Policy.
     */
    protected function applyCsp(Response $response): void
    {
        // Use Content-Security-Policy-Report-Only in development
        // Exclude upgrade-insecure-requests in Report-Only mode as it's not valid there
        if (app()->environment('local', 'development')) {
            $csp = $this->buildCsp(true);
            $response->headers->set('Content-Security-Policy-Report-Only', $csp);
        } else {
            $csp = $this->buildCsp(false);
            $response->headers->set('Content-Security-Policy', $csp);
        }
    }

    /**
     * Build Content Security Policy string.
     *
     * @param  bool  $reportOnly  Whether building for Report-Only mode
     */
    protected function buildCsp(bool $reportOnly = false): string
    {
        $directives = [];

        foreach ($this->cspDirectives as $directive => $value) {
            // Skip upgrade-insecure-requests in Report-Only mode (not valid there)
            if ($reportOnly && $directive === 'upgrade-insecure-requests') {
                continue;
            }

            if ($value === '') {
                $directives[] = $directive;
            } else {
                $directives[] = "{$directive} {$value}";
            }
        }

        return implode('; ', $directives);
    }

    /**
     * Apply HTTP Strict Transport Security (HSTS).
     *
     * Only applied in production environments with HTTPS.
     */
    protected function applyHsts(Response $response): void
    {
        if (! app()->environment('production')) {
            return;
        }

        // Check if request is HTTPS
        if (! request()->secure()) {
            return;
        }

        $maxAge = config('security.hsts_max_age', 31536000); // 1 year default
        $includeSubDomains = config('security.hsts_include_subdomains', true);
        $preload = config('security.hsts_preload', false);

        $hstsValue = "max-age={$maxAge}";

        if ($includeSubDomains) {
            $hstsValue .= '; includeSubDomains';
        }

        if ($preload) {
            $hstsValue .= '; preload';
        }

        $response->headers->set('Strict-Transport-Security', $hstsValue);
    }

    /**
     * Remove headers that leak sensitive information.
     */
    protected function removeSensitiveHeaders(Response $response): void
    {
        // Remove server identification
        $response->headers->remove('Server');
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('X-Generator');
    }
}
