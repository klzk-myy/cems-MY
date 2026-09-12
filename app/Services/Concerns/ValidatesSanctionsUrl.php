<?php

namespace App\Services\Concerns;

/**
 * Shared SSRF guard for the sanctions download/import pipeline.
 *
 * Single source of truth for the source-URL allowlist so the orchestration
 * and download services cannot drift apart. Hosts come from
 * config('sanctions.allowed_hosts') so adding a source is a config change,
 * not a code change.
 */
trait ValidatesSanctionsUrl
{
    protected function validateUrl(string $url): void
    {
        $parsed = parse_url($url);
        if ($parsed === false || empty($parsed['scheme']) || empty($parsed['host'])) {
            throw new \InvalidArgumentException("Invalid URL: {$url}");
        }

        if ($parsed['scheme'] !== 'https') {
            throw new \InvalidArgumentException("Only HTTPS URLs are allowed: {$url}");
        }

        $host = strtolower($parsed['host']);
        $allowedHosts = config('sanctions.allowed_hosts', []);

        $isAllowed = false;
        foreach ($allowedHosts as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                $isAllowed = true;
                break;
            }
        }

        if (! $isAllowed) {
            throw new \InvalidArgumentException("Host not in sanctions URL allowlist: {$host}");
        }

        $ip = gethostbyname($host);
        if ($ip !== $host && ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new \InvalidArgumentException("URL resolves to private/internal IP: {$ip}");
        }
    }
}
