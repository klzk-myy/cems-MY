<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Route Verification Script Test
 *
 * Tests the route verification script functionality.
 */
class RouteVerificationTest extends TestCase
{
    protected string $scriptPath;

    protected string $tempViewsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scriptPath = base_path('scripts/check-routes.php');
        $this->tempViewsPath = storage_path('framework/testing/views');

        // Ensure temp directory exists
        if (! is_dir($this->tempViewsPath)) {
            mkdir($this->tempViewsPath, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        // Clean up temp directory
        if (is_dir($this->tempViewsPath)) {
            File::deleteDirectory($this->tempViewsPath);
        }

        parent::tearDown();
    }

    #[Test]
    public function script_exists()
    {
        $this->assertFileExists($this->scriptPath);
    }

    #[Test]
    public function script_is_executable()
    {
        $this->assertIsReadable($this->scriptPath);
    }

    #[Test]
    public function script_detects_valid_routes()
    {
        // Create a test view with valid route references
        File::put($this->tempViewsPath.'/test.blade.php', <<<'BLADE'
            <a href="{{ route('home') }}">Home</a>
            <a href="{{ route('login') }}">Login</a>
            <form action="{{ route('logout') }}" method="POST">
            BLADE);

        $output = $this->runScript(['--path='.$this->tempViewsPath]);

        $this->assertStringContainsString('All route references in views are valid', $output);
        $this->assertStringContainsString('Total route references in views: 3', $output);
    }

    #[Test]
    public function script_detects_missing_routes()
    {
        // Create a test view with invalid route references
        File::put($this->tempViewsPath.'/test.blade.php', <<<'BLADE'
            <a href="{{ route('nonexistent.route') }}">Link</a>
            BLADE);

        $output = $this->runScript(['--path='.$this->tempViewsPath]);

        $this->assertStringContainsString('Found 1 missing route(s)', $output);
        $this->assertStringContainsString('nonexistent.route', $output);
    }

    #[Test]
    public function script_handles_double_quoted_routes()
    {
        // Create a test view with double-quoted route references
        File::put($this->tempViewsPath.'/test.blade.php', <<<'BLADE'
            <a href="{{ route("home") }}">Home</a>
            <a href="{{ route("login") }}">Login</a>
            BLADE);

        $output = $this->runScript(['--path='.$this->tempViewsPath]);

        $this->assertStringContainsString('Total route references in views: 2', $output);
    }

    #[Test]
    public function script_handles_routes_with_parameters()
    {
        // Create a test view with route references that have parameters
        File::put($this->tempViewsPath.'/test.blade.php', <<<'BLADE'
            <a href="{{ route('users.show', ['id' => 1]) }}">User</a>
            <a href="{{ route('posts.edit', $post) }}">Edit</a>
            BLADE);

        $output = $this->runScript(['--path='.$this->tempViewsPath]);

        $this->assertStringContainsString('Total route references in views: 2', $output);
    }

    #[Test]
    public function script_handles_mixed_quote_styles()
    {
        // Create a test view with mixed quote styles
        File::put($this->tempViewsPath.'/test.blade.php', <<<'BLADE'
            <a href="{{ route('home') }}">Home</a>
            <a href="{{ route("login") }}">Login</a>
            <a href="{{ route('dashboard') }}">Dashboard</a>
            BLADE);

        $output = $this->runScript(['--path='.$this->tempViewsPath]);

        $this->assertStringContainsString('Total route references in views: 3', $output);
    }

    #[Test]
    public function script_outputs_json_format()
    {
        // Create a test view
        File::put($this->tempViewsPath.'/test.blade.php', <<<'BLADE'
            <a href="{{ route('home') }}">Home</a>
            BLADE);

        $output = $this->runScript(['--format=json', '--path='.$this->tempViewsPath]);

        $this->assertJson($output);

        $data = json_decode($output, true);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('route_references', $data);
        $this->assertArrayHasKey('defined_routes', $data);
        $this->assertArrayHasKey('missing_routes', $data);
        $this->assertArrayHasKey('summary', $data);
    }

    #[Test]
    public function json_format_includes_all_data()
    {
        // Create a test view with both valid and invalid routes
        File::put($this->tempViewsPath.'/test.blade.php', <<<'BLADE'
            <a href="{{ route('home') }}">Home</a>
            <a href="{{ route('nonexistent.route') }}">Link</a>
            BLADE);

        $output = $this->runScript(['--format=json', '--path='.$this->tempViewsPath]);

        $data = json_decode($output, true);

        $this->assertIsArray($data['route_references']);
        $this->assertIsArray($data['defined_routes']);
        $this->assertIsArray($data['missing_routes']);
        $this->assertIsArray($data['summary']);

        $this->assertContains('home', $data['route_references']);
        $this->assertContains('nonexistent.route', $data['route_references']);
        $this->assertContains('nonexistent.route', $data['missing_routes']);
    }

    #[Test]
    public function script_handles_empty_views_directory()
    {
        // Create empty views directory
        if (! is_dir($this->tempViewsPath)) {
            mkdir($this->tempViewsPath, 0755, true);
        }

        $output = $this->runScript(['--path='.$this->tempViewsPath]);

        $this->assertStringContainsString('Found 0 unique route references in views', $output);
        $this->assertStringContainsString('All route references in views are valid', $output);
    }

    #[Test]
    public function script_exits_with_zero_on_success()
    {
        // Create a test view with valid routes
        File::put($this->tempViewsPath.'/test.blade.php', <<<'BLADE'
            <a href="{{ route('home') }}">Home</a>
            BLADE);

        $result = $this->runScriptWithExitCode(['--path='.$this->tempViewsPath]);

        $this->assertEquals(0, $result);
    }

    #[Test]
    public function script_exits_with_one_on_missing_routes()
    {
        // Create a test view with invalid routes
        File::put($this->tempViewsPath.'/test.blade.php', <<<'BLADE'
            <a href="{{ route('nonexistent.route') }}">Link</a>
            BLADE);

        $result = $this->runScriptWithExitCode(['--path='.$this->tempViewsPath]);

        $this->assertEquals(1, $result);
    }

    #[Test]
    public function script_exits_with_two_on_error()
    {
        // Use a non-existent path
        $result = $this->runScriptWithExitCode(['--path=/nonexistent/path']);

        $this->assertEquals(2, $result);
    }

    #[Test]
    public function script_ignores_non_php_files()
    {
        // Create test files with different extensions
        File::put($this->tempViewsPath.'/test.blade.php', <<<'BLADE'
            <a href="{{ route('home') }}">Home</a>
            BLADE);
        File::put($this->tempViewsPath.'/test.txt', 'route("login")');
        File::put($this->tempViewsPath.'/test.html', '<a href="{{ route("dashboard") }}">Dashboard</a>');

        $output = $this->runScript(['--path='.$this->tempViewsPath]);

        $this->assertStringContainsString('Total route references in views: 1', $output);
    }

    #[Test]
    public function script_handles_multiple_files()
    {
        // Create multiple test files
        File::put($this->tempViewsPath.'/file1.blade.php', <<<'BLADE'
            <a href="{{ route('home') }}">Home</a>
            <a href="{{ route('login') }}">Login</a>
            BLADE);
        File::put($this->tempViewsPath.'/file2.blade.php', <<<'BLADE'
            <a href="{{ route('dashboard') }}">Dashboard</a>
            BLADE);

        $output = $this->runScript(['--path='.$this->tempViewsPath]);

        $this->assertStringContainsString('Total route references in views: 3', $output);
    }

    #[Test]
    public function script_deduplicates_route_references()
    {
        // Create a test view with duplicate route references
        File::put($this->tempViewsPath.'/test.blade.php', <<<'BLADE'
            <a href="{{ route('home') }}">Home</a>
            <a href="{{ route('home') }}">Home Again</a>
            <a href="{{ route('home') }}">Home Once More</a>
            BLADE);

        $output = $this->runScript(['--path='.$this->tempViewsPath]);

        $this->assertStringContainsString('Total route references in views: 1', $output);
    }

    /**
     * Run the script and return the output.
     */
    protected function runScript(array $args = []): string
    {
        $command = 'php -d error_reporting=0 '.escapeshellarg($this->scriptPath).' '.implode(' ', array_map('escapeshellarg', $args)).' 2>/dev/null';

        exec($command, $output, $exitCode);

        return implode("\n", $output);
    }

    /**
     * Run the script and return the exit code.
     */
    protected function runScriptWithExitCode(array $args = []): int
    {
        $command = 'php '.escapeshellarg($this->scriptPath).' '.implode(' ', array_map('escapeshellarg', $args)).' 2>&1';

        exec($command, $output, $exitCode);

        return $exitCode;
    }
}
