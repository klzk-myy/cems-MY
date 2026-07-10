<?php

declare(strict_types=1);

namespace App\Services\Security;

/**
 * Centralized service for IP address validation and allowlist/blocklist checks.
 *
 * Supports exact IPv4/IPv6 addresses and CIDR notation for IPv4 ranges.
 */
class IpValidationService
{
    /**
     * Determine whether an IP is allowed given an allowlist and blocklist.
     *
     * Blocklist entries are evaluated first. An empty allowlist means any IP
     * (that is not blocked) is allowed. Allowlist entries may be exact IPs or
     * IPv4 CIDR ranges (e.g. 192.168.1.0/24).
     */
    public function isAllowed(string $ip, array $allowlist = [], array $blocklist = []): bool
    {
        if (in_array($ip, $blocklist, true)) {
            return false;
        }

        if ($allowlist === []) {
            return true;
        }

        foreach ($allowlist as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }

            if (str_contains($entry, '/')) {
                if ($this->ipInCidr($ip, $entry)) {
                    return true;
                }
            } elseif ($entry === $ip) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate that a string is a valid IPv4 or IPv6 address.
     */
    public function isValidIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Check whether an IPv4 address falls within a CIDR range.
     */
    public function ipInCidr(string $ip, string $cidr): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        [$subnet, $mask] = array_pad(explode('/', $cidr, 2), 2, null);

        if ($subnet === null || $mask === null || ! is_numeric($mask)) {
            return false;
        }

        $mask = (int) $mask;
        if ($mask < 0 || $mask > 32) {
            return false;
        }

        if (! filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $maskLong = -1 << (32 - $mask);

        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }
}
