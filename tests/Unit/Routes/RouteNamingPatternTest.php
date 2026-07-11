<?php

namespace Tests\Unit\Routes;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RouteNamingPatternTest extends TestCase
{
    /**
     * Vendor packages whose route names we do not control.
     *
     * @var array<int, string>
     */
    protected array $vendorPrefixes = [
        'ignition.',
        'sanctum.',
        'livewire.',
        'filament.',
        'horizon.',
        'telescope.',
    ];

    #[Test]
    public function application_route_names_use_kebab_case_segments(): void
    {
        $violations = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if (empty($name)) {
                continue;
            }

            if ($this->isVendorRoute($name)) {
                continue;
            }

            // Split on dots to check each segment.
            $segments = explode('.', $name);

            foreach ($segments as $segment) {
                if (empty($segment)) {
                    continue;
                }

                // Disallow camelCase and snake_case; allow lowercase letters, digits, and hyphens.
                if (preg_match('/[A-Z_]/', $segment)) {
                    $violations[] = "{$name} (segment: {$segment})";
                }
            }
        }

        $this->assertEmpty(
            $violations,
            'Application route names must use kebab-case segments. Violations: '.implode(', ', $violations)
        );
    }

    private function isVendorRoute(string $name): bool
    {
        foreach ($this->vendorPrefixes as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
