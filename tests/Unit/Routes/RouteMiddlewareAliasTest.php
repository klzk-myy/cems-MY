<?php

namespace Tests\Unit\Routes;

use App\Enums\Permission;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RouteMiddlewareAliasTest extends TestCase
{
    /**
     * Vendor packages whose route middleware we do not control.
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
    public function application_routes_use_named_middleware_aliases(): void
    {
        $violations = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = $route->getName() ?? $route->uri();

            if ($this->isVendorRoute($name)) {
                continue;
            }

            foreach ($route->middleware() as $middleware) {
                // Skip aliases and built-in middleware short names.
                if (! str_contains($middleware, '\\')) {
                    continue;
                }

                $violations[] = "{$name}: {$middleware}";
            }
        }

        $this->assertEmpty(
            $violations,
            'Application routes must use named middleware aliases instead of inline class strings. Violations: '.implode(', ', $violations)
        );
    }

    /**
     * Every `role:` middleware parameter must resolve through
     * CheckRole → UserRole::matchesRoleAlias(): a legacy alias or a
     * Permission enum key. A typo fails loudly at request time (CheckRole
     * throws InvalidArgumentException); this test catches it earlier, at
     * route registration.
     */
    #[Test]
    public function role_middleware_uses_only_canonical_role_names(): void
    {
        // Mirrors UserRole::matchesRoleAlias(): identity aliases plus the
        // effective-permission module aliases, or any Permission key.
        $canonical = ['admin', 'manager', 'compliance', 'accountant', 'accounting', 'users', 'teller'];
        $permissionKeys = array_column(Permission::cases(), 'value');
        $violations = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = $route->getName() ?? $route->uri();

            foreach ($route->middleware() as $middleware) {
                if (! str_starts_with($middleware, 'role:')) {
                    continue;
                }

                foreach (explode(',', substr($middleware, 5)) as $role) {
                    if (! in_array($role, $canonical, true) && ! in_array($role, $permissionKeys, true)) {
                        $violations[] = "{$name}: role:{$role}";
                    }
                }
            }
        }

        $this->assertEmpty(
            $violations,
            'Routes must use canonical role aliases or Permission keys. Violations: '.implode(', ', $violations)
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
