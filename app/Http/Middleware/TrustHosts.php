<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustHosts as Middleware;

class TrustHosts extends Middleware
{
    /**
     * Get the host patterns that should be trusted.
     *
     * @return array<int, string|null>
     */
    public function hosts(): array
    {
        return [
            $this->allSubdomainsOfApplicationUrl(),
            '^192\.168\.\d+\.\d+$', // Allow all 192.168.x.x LAN IPs
            '^10\.\d+\.\d+\.\d+$',   // Allow all 10.x.x.x private IPs
            '^172\.(1[6-9]|2[0-9]|3[01])\.\d+\.\d+$', // Allow 172.16-31.x.x
            '.*', // Allow any host (fallback)
        ];
    }
}
