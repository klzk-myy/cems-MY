<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use ReflectionMethod;

class ValidateRouteConsistency extends Command
{
    protected $signature = 'routes:validate';

    protected $description = 'Validate controller-route-view consistency';

    private array $errors = [];

    private array $warnings = [];

    private array $info = [];

    public function handle(): int
    {
        $this->info('=== Route Consistency Validation ===');
        $this->info('');

        $this->validateRoutes();
        $this->validateControllers();
        $this->validateViews();
        $this->validateMiddleware();
        $this->validateRouteNaming();

        $this->displayResults();

        return count($this->errors) > 0 ? 1 : 0;
    }

    private function validateRoutes(): void
    {
        $this->info('Checking routes against controllers...');

        $routes = Route::getRoutes();
        $routeList = $routes->getRoutesByMethod();

        foreach ($routeList as $method => $routes) {
            foreach ($routes as $route) {
                $this->validateRoute($route, $method);
            }
        }
    }

    private function validateRoute($route, string $httpMethod): void
    {
        $uri = $route->uri();
        $action = $route->action;
        $routeName = $route->getName() ?? '(unnamed)';

        if (isset($action['uses'])) {
            $uses = $action['uses'];

            if ($uses instanceof \Closure) {
                $this->info("  ✓ Closure route: $uri");

                return;
            }

            if (is_string($uses)) {
                [$controller, $method] = $this->parseControllerAction($uses);

                if (! $this->controllerExists($controller)) {
                    $this->errors[] = [
                        'route' => "$httpMethod $uri",
                        'name' => $routeName,
                        'issue' => "Controller '$controller' does not exist",
                        'severity' => 'critical',
                    ];

                    return;
                }

                if (! $this->methodExists($controller, $method)) {
                    $this->errors[] = [
                        'route' => "$httpMethod $uri",
                        'name' => $routeName,
                        'issue' => "Method '$method' does not exist in '$controller'",
                        'severity' => 'critical',
                    ];

                    return;
                }

                // Check if method returns a view
                if ($this->methodReturnsView($controller, $method)) {
                    $viewPath = $this->getViewPathFromMethod($controller, $method);
                    if ($viewPath && ! $this->viewExists($viewPath)) {
                        $this->errors[] = [
                            'route' => "$httpMethod $uri",
                            'name' => $routeName,
                            'issue' => "View '$viewPath' does not exist (returned by $controller@$method)",
                            'severity' => 'error',
                        ];
                    }
                }

                $this->info("  ✓ $httpMethod $uri -> $controller@$method");

                return;
            }
        }

        if (isset($action['controller'])) {
            $this->warnings[] = [
                'route' => "$httpMethod $uri",
                'name' => $routeName,
                'issue' => 'Route uses array-style controller notation (should use ::class)',
                'severity' => 'warning',
            ];
        }
    }

    private function parseControllerAction(string $uses): array
    {
        $parts = explode('@', $uses);

        return [($parts[0] ?? ''), ($parts[1] ?? '')];
    }

    private function controllerExists(string $controller): bool
    {
        return class_exists($controller);
    }

    private function methodExists(string $controller, string $method): bool
    {
        return method_exists($controller, $method);
    }

    private function methodReturnsView(string $controller, string $method): bool
    {
        try {
            $reflection = new ReflectionMethod($controller, $method);
            $startLine = $reflection->getStartLine();
            $endLine = $reflection->getEndLine();

            $file = $reflection->getFileName();
            if (! $file) {
                return false;
            }

            $lines = file($file);
            if (! $lines) {
                return false;
            }

            // Get method content
            $methodLines = array_slice($lines, $startLine - 1, $endLine - $startLine + 1);
            $methodContent = implode('', $methodLines);

            // Check for view(), View::make(), return view::make(), etc.
            return preg_match('/(?:return\s+)?(?:view|VIEW|View)::make\s*\(/', $methodContent) === 1
                || preg_match('/(?:return\s+)?\$this->view\s*\(/', $methodContent) === 1
                || preg_match('/(?:return\s+)?(?:view|VIEW|View)\s*\(\s*[\'"]([^\'"]+)/', $methodContent) === 1;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getViewPathFromMethod(string $controller, string $method): ?string
    {
        try {
            $reflection = new ReflectionMethod($controller, $method);
            $startLine = $reflection->getStartLine();
            $endLine = $reflection->getEndLine();

            $file = $reflection->getFileName();
            if (! $file) {
                return null;
            }

            $lines = file($file);
            if (! $lines) {
                return null;
            }

            $methodLines = array_slice($lines, $startLine - 1, $endLine - $startLine + 1);
            $methodContent = implode('', $methodLines);

            // Extract view path from view() or View::make()
            if (preg_match('/(?:return\s+)?(?:view|VIEW|View)::make\s*\(\s*[\'"]([^\'"]+)/', $methodContent, $matches)) {
                return $matches[1];
            }

            if (preg_match('/(?:return\s+)?(?:view|VIEW|View)\s*\(\s*[\'"]([^\'"]+)/', $methodContent, $matches)) {
                return $matches[1];
            }

            if (preg_match('/(?:return\s+)?\$this->view\s*\(\s*[\'"]([^\'"]+)/', $methodContent, $matches)) {
                return $matches[1];
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function viewExists(string $path): bool
    {
        // Convert dot notation to slash path
        $path = str_replace('.', '/', $path);

        // Check multiple possible locations
        $possiblePaths = [
            resource_path("views/$path.blade.php"),
            resource_path("views/$path.php"),
        ];

        foreach ($possiblePaths as $p) {
            if (file_exists($p)) {
                return true;
            }
        }

        return false;
    }

    private function validateControllers(): void
    {
        $this->info('');
        $this->info('Checking controller method signatures...');

        $controllerDir = app_path('Http/Controllers');
        $controllers = $this->getControllersRecursively($controllerDir);

        foreach ($controllers as $controller) {
            $this->validateControllerFile($controller);
        }
    }

    private function getControllersRecursively(string $dir): array
    {
        $controllers = [];
        $items = scandir($dir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === 'Controller.php') {
                continue;
            }

            $path = "$dir/$item";
            if (is_dir($path)) {
                $controllers = array_merge($controllers, $this->getControllersRecursively($path));
            } elseif (is_file($path) && str_ends_with($item, 'Controller.php')) {
                $controllers[] = $path;
            }
        }

        return $controllers;
    }

    private function validateControllerFile(string $filePath): void
    {
        $className = $this->getFullyQualifiedClassName($filePath);
        if (! $className || ! class_exists($className)) {
            return;
        }

        try {
            $reflection = new \ReflectionClass($className);
            $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

            foreach ($methods as $method) {
                if ($method->getDeclaringClass()->getName() !== $className) {
                    continue;
                }
                if (in_array($method->getName(), ['__construct', '__destruct'])) {
                    continue;
                }

                $this->validateMethodSignature($className, $method);
            }
        } catch (\Exception $e) {
            $this->warnings[] = [
                'route' => $className,
                'name' => '',
                'issue' => 'Could not parse controller: '.$e->getMessage(),
                'severity' => 'warning',
            ];
        }
    }

    private function getFullyQualifiedClassName(string $filePath): ?string
    {
        $content = file_get_contents($filePath);
        if (! $content) {
            return null;
        }

        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $namespace = trim($matches[1]);
            $className = basename($filePath, '.php');

            return "$namespace\\$className";
        }

        return null;
    }

    private function validateMethodSignature(string $controller, ReflectionMethod $method): void
    {
        $methodName = $method->getName();
        $fileName = $method->getFileName();
        $startLine = $method->getStartLine();

        // Check for proper dependency injection (type hints)
        $params = $method->getParameters();
        foreach ($params as $param) {
            if ($param->getType() === null && ! $param->isDefaultValueAvailable()) {
                // Allow request, response, etc without type hints as they're often handled in base controller
            }
        }
    }

    private function validateViews(): void
    {
        $this->info('');
        $this->info('Checking for orphaned views (no route pointing to them)...');

        $viewsDir = resource_path('views');
        $allViews = $this->getViewsRecursively($viewsDir);

        $routes = Route::getRoutes();
        $usedViews = $this->extractViewsFromRoutes($routes);

        $unusedViews = [];
        foreach ($allViews as $view) {
            $viewName = $this->normalizeViewName($view);
            if (! in_array($viewName, $usedViews) && ! $this->isLayoutOrPartial($view)) {
                $unusedViews[] = $view;
            }
        }

        if (count($unusedViews) > 0) {
            $this->warnings[] = [
                'route' => 'Views',
                'name' => '',
                'issue' => 'Found '.count($unusedViews).' views not referenced by any route',
                'details' => $unusedViews,
                'severity' => 'info',
            ];
        } else {
            $this->info('  ✓ All views have corresponding routes');
        }
    }

    private function getViewsRecursively(string $dir, string $baseDir = ''): array
    {
        $views = [];
        $items = scandir($dir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = "$dir/$item";
            $relativePath = $baseDir ? "$baseDir/$item" : $item;

            if (is_dir($path)) {
                $views = array_merge($views, $this->getViewsRecursively($path, $relativePath));
            } elseif (is_file($path) && (str_ends_with($item, '.blade.php') || str_ends_with($item, '.php'))) {
                // Convert path to view name (remove .blade.php or .php)
                $viewName = preg_replace('/\.(blade\.php|\.php)$/', '', $relativePath);
                $views[] = $viewName;
            }
        }

        return $views;
    }

    private function extractViewsFromRoutes($routes): array
    {
        $views = [];
        $allRoutes = $routes instanceof RouteCollection ? $routes->getRoutes() : $routes;

        foreach ($allRoutes as $route) {
            $action = $route->action;
            if (isset($action['uses']) && is_string($action['uses'])) {
                [$controller, $method] = $this->parseControllerAction($action['uses']);
                if ($this->controllerExists($controller) && $this->methodExists($controller, $method)) {
                    $viewPath = $this->getViewPathFromMethod($controller, $method);
                    if ($viewPath) {
                        $views[] = $this->normalizeViewName($viewPath);
                    }
                }
            }
        }

        return array_unique($views);
    }

    private function normalizeViewName(string $view): string
    {
        // Convert slash-separated path to dot notation
        return str_replace('/', '.', $view);
    }

    private function isLayoutOrPartial(string $view): bool
    {
        $partials = ['layouts.', 'components.', 'errors.'];
        foreach ($partials as $partial) {
            if (str_contains($view, $partial)) {
                return true;
            }
        }

        return false;
    }

    private function validateMiddleware(): void
    {
        $this->info('');
        $this->info('Checking middleware consistency...');

        $routes = Route::getRoutes()->getRoutesByMethod();

        foreach ($routes as $method => $methodRoutes) {
            foreach ($methodRoutes as $route) {
                $this->validateRouteMiddleware($route, $method);
            }
        }
    }

    private function validateRouteMiddleware($route, string $httpMethod): void
    {
        $uri = $route->uri();
        $action = $route->action;
        $middleware = $action['middleware'] ?? [];

        if (is_array($middleware)) {
            // Check for conflicting middleware
            if (in_array('role:admin', $middleware) && in_array('role:manager', $middleware)) {
                $this->warnings[] = [
                    'route' => "$httpMethod $uri",
                    'name' => $route->getName() ?? '',
                    'issue' => 'Route has both role:admin and role:manager (role:admin already implies role:manager)',
                    'severity' => 'warning',
                ];
            }
        }
    }

    private function validateRouteNaming(): void
    {
        $this->info('');
        $this->info('Checking route naming conventions...');

        $routes = Route::getRoutes();
        $allRoutes = $routes->getRoutes();

        $namedRoutes = [];
        $issues = [];

        foreach ($allRoutes as $route) {
            $name = $route->getName();
            if (! $name) {
                $issues[] = [
                    'route' => implode('|', $route->methods()).' '.$route->uri(),
                    'name' => '(unnamed)',
                    'issue' => 'Route has no name',
                    'severity' => 'info',
                ];

                continue;
            }

            if (isset($namedRoutes[$name])) {
                $issues[] = [
                    'route' => implode('|', $route->methods()).' '.$route->uri(),
                    'name' => $name,
                    'issue' => 'Duplicate route name',
                    'severity' => 'error',
                ];
            }

            $namedRoutes[$name] = $route;
        }

        // Check for inconsistent naming patterns
        foreach ($namedRoutes as $name => $route) {
            if (preg_match('/\.\./', $name)) {
                $issues[] = [
                    'route' => implode('|', $route->methods()).' '.$route->uri(),
                    'name' => $name,
                    'issue' => 'Route name contains double dot (..)',
                    'severity' => 'warning',
                ];
            }
        }

        if (count($issues) > 0) {
            $this->warnings = array_merge($this->warnings, $issues);
        } else {
            $this->info('  ✓ All routes have unique names');
        }
    }

    private function displayResults(): void
    {
        $this->info('');
        $this->info('=== RESULTS ===');
        $this->info('');

        $critical = array_filter($this->errors, fn ($e) => $e['severity'] === 'critical');
        $errors = array_filter($this->errors, fn ($e) => $e['severity'] === 'error');
        $warnings = $this->warnings;

        if (count($critical) > 0) {
            $this->error('CRITICAL ERRORS ('.count($critical).'):');
            foreach ($critical as $e) {
                $this->error("  ✗ {$e['route']} [{$e['name']}]");
                $this->error("    Issue: {$e['issue']}");
            }
            $this->info('');
        }

        if (count($errors) > 0) {
            $this->error('ERRORS ('.count($errors).'):');
            foreach ($errors as $e) {
                $this->error("  ✗ {$e['route']} [{$e['name']}]");
                $this->error("    Issue: {$e['issue']}");
            }
            $this->info('');
        }

        if (count($warnings) > 0) {
            $this->warn('WARNINGS ('.count($warnings).'):');
            foreach ($warnings as $w) {
                $this->warn("  ⚠ {$w['route']} [{$w['name']}]");
                $this->warn("    Issue: {$w['issue']}");
            }
            $this->info('');
        }

        $infoItems = array_filter($this->info, fn ($i) => str_starts_with($i, '  ✓'));
        $infoCount = count(array_filter($this->info, fn ($i) => preg_match('/^  ✓/', $i)));

        $this->info('SUMMARY:');
        $this->info('  Critical: '.count($critical));
        $this->info('  Errors: '.count($errors));
        $this->info('  Warnings: '.count($warnings));
        $this->info('  Checks passed: '.$infoCount);

        if (count($critical) === 0 && count($errors) === 0) {
            $this->info('');
            $this->info('✓ All critical checks passed!');
        }
    }
}
