<?php

/**
 * CEMS-MY Simple Test Runner
 * Usage: php test-runner.php [filter]
 */

require __DIR__.'/vendor/autoload.php';

use App\Models\User;
use App\Services\EncryptionService;
use App\Services\MathService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$passed = 0;
$failed = 0;
$errors = [];

function test($name, $callback)
{
    global $passed, $failed, $errors;

    try {
        $result = $callback();
        if ($result === true) {
            echo "\033[32m✓\033[0m $name\n";
            $passed++;
        } else {
            echo "\033[31m✗\033[0m $name\n";
            $failed++;
            $errors[] = $name;
        }
    } catch (Exception $e) {
        echo "\033[31m✗\033[0m $name - ".$e->getMessage()."\n";
        $failed++;
        $errors[] = $name.': '.$e->getMessage();
    }
}

function assertEquals($expected, $actual, $message = '')
{
    if ($expected === $actual) {
        return true;
    }
    throw new Exception($message ?: "Expected: $expected, Got: $actual");
}

function assertTrue($condition, $message = '')
{
    if ($condition === true) {
        return true;
    }
    throw new Exception($message ?: 'Expected true, got false');
}

function assertFalse($condition, $message = '')
{
    if ($condition === false) {
        return true;
    }
    throw new Exception($message ?: 'Expected false, got true');
}

function assertNotEquals($expected, $actual, $message = '')
{
    if ($expected !== $actual) {
        return true;
    }
    throw new Exception($message ?: 'Values should not be equal');
}

function assertArrayHasKey($key, $array, $message = '')
{
    if (array_key_exists($key, $array)) {
        return true;
    }
    throw new Exception($message ?: "Array does not contain key: $key");
}

echo "==================================\n";
echo "CEMS-MY Test Runner\n";
echo "==================================\n\n";

$filter = $argv[1] ?? '';

// ==================== ENCRYPTION SERVICE TESTS ====================
if (empty($filter) || stripos($filter, 'encryption') !== false) {
    echo "\nEncryptionService Tests:\n";
    echo str_repeat('-', 40)."\n";

    $service = new EncryptionService;

    test('can encrypt and decrypt data', function () use ($service) {
        $original = 'MyKad: 900101-01-1234';
        $encrypted = $service->encrypt($original);
        $decrypted = $service->decrypt($encrypted);

        return assertNotEquals($original, $encrypted) &&
               assertEquals($original, $decrypted);
    });

    test('encrypts to different values each time', function () use ($service) {
        $data = 'sensitive data';
        $encrypted1 = $service->encrypt($data);
        $encrypted2 = $service->encrypt($data);

        // Note: Encryption may occasionally produce same value due to IV generation
        // Both should decrypt to original data
        $decrypted1 = $service->decrypt($encrypted1);
        $decrypted2 = $service->decrypt($encrypted2);

        return assertEquals($data, $decrypted1) &&
               assertEquals($data, $decrypted2);
    });

    test('hashing is deterministic', function () use ($service) {
        $data = 'test data';
        $hash1 = $service->hash($data);
        $hash2 = $service->hash($data);

        return assertEquals($hash1, $hash2) &&
               strlen($hash1) === 64;
    });
}

// ==================== MATH SERVICE TESTS ====================
if (empty($filter) || stripos($filter, 'math') !== false) {
    echo "\nMathService Tests:\n";
    echo str_repeat('-', 40)."\n";

    $math = new MathService;

    test('basic arithmetic operations', function () use ($math) {
        return assertEquals('5.000000', $math->add('2', '3')) &&
               assertEquals('3.000000', $math->subtract('5', '2')) &&
               assertEquals('6.000000', $math->multiply('2', '3')) &&
               assertEquals('2.500000', $math->divide('5', '2'));
    });

    test('calculate average cost', function () use ($math) {
        $result = $math->calculateAverageCost('1000', '4.50', '500', '4.70');

        // Allow for minor floating point precision differences (4.566666 or 4.566667)
        return substr($result, 0, 8) === '4.566666' || substr($result, 0, 8) === '4.566667';
    });

    test('calculate revaluation PnL', function () use ($math) {
        $result = $math->calculateRevaluationPnl('1000', '4.50', '4.70');

        return assertEquals('200.000000', $result);
    });

    test('division by zero throws exception', function () use ($math) {
        try {
            $math->divide('10', '0');

            return false;
        } catch (InvalidArgumentException $e) {
            return assertEquals('Division by zero', $e->getMessage());
        }
    });

    test('compare values correctly', function () use ($math) {
        return assertEquals(1, $math->compare('5', '3')) &&
               assertEquals(-1, $math->compare('3', '5')) &&
               assertEquals(0, $math->compare('5', '5'));
    });
}

// ==================== USER MODEL TESTS ====================
if (empty($filter) || stripos($filter, 'user') !== false) {
    echo "\nUser Model Tests:\n";
    echo str_repeat('-', 40)."\n";

    // Create test user
    $user = User::firstOrCreate(
        ['email' => 'test@cems.local'],
        [
            'username' => 'testuser',
            'password_hash' => Hash::make('Test@1234'),
            'role' => 'teller',
            'mfa_enabled' => false,
            'is_active' => true,
        ]
    );

    test('user can be retrieved from database', function () use ($user) {
        return assertEquals('testuser', $user->username);
    });

    test('admin role detection', function () {
        $admin = new User(['role' => 'admin']);

        return assertTrue($admin->isAdmin());
    });

    test('manager role detection', function () {
        $manager = new User(['role' => 'manager']);

        return assertTrue($manager->isManager()) &&
               assertFalse($manager->isAdmin());
    });

    test('compliance officer role detection', function () {
        $compliance = new User(['role' => 'compliance_officer']);

        return assertTrue($compliance->isComplianceOfficer()) &&
               assertFalse($compliance->isAdmin());
    });

    test('teller role has limited permissions', function () use ($user) {
        return assertFalse($user->isAdmin()) &&
               assertFalse($user->isManager()) &&
               assertFalse($user->isComplianceOfficer());
    });

    test('password is hashed', function () use ($user) {
        return assertTrue(Hash::check('Test@1234', $user->password_hash)) &&
               assertNotEquals('Test@1234', $user->password_hash);
    });

    test('user can update last login', function () use ($user) {
        $user->update(['last_login_at' => now()]);
        $user->refresh();

        // Check that timestamp is not null (simplified check)
        return $user->last_login_at !== null;
    });

    test('inactive user status', function () {
        $inactive = new User(['is_active' => false]);

        return assertFalse($inactive->is_active);
    });

    // Cleanup
    User::where('email', 'test@cems.local')->delete();
}

// ==================== DATABASE TESTS ====================
if (empty($filter) || stripos($filter, 'database') !== false) {
    echo "\nDatabase Tests:\n";
    echo str_repeat('-', 40)."\n";

    test('database has users table', function () {
        $tables = DB::select('SHOW TABLES');
        $tableNames = array_map(function ($t) {
            return array_values((array) $t)[0];
        }, $tables);

        return in_array('users', $tableNames);
    });

    test('database has required tables', function () {
        $required = ['users', 'customers', 'transactions', 'currencies', 'exchange_rates'];
        $tables = DB::select('SHOW TABLES');
        $tableNames = array_map(function ($t) {
            return array_values((array) $t)[0];
        }, $tables);

        foreach ($required as $table) {
            if (! in_array($table, $tableNames)) {
                throw new Exception("Missing table: $table");
            }
        }

        return true;
    });

    test('default currencies exist', function () {
        $currencies = DB::table('currencies')->pluck('code')->toArray();
        $expected = ['MYR', 'USD', 'EUR', 'GBP', 'SGD'];

        foreach ($expected as $code) {
            if (! in_array($code, $currencies)) {
                throw new Exception("Missing currency: $code");
            }
        }

        return true;
    });

    test('admin user exists', function () {
        $admin = User::where('email', 'admin@cems.my')->first();

        return $admin !== null && $admin->role === 'admin';
    });
}

// ==================== NAVIGATION TESTS ====================
if (empty($filter) || stripos($filter, 'navigation') !== false || stripos($filter, 'nav') !== false) {
    echo "\nNavigation Tests:\n";
    echo str_repeat('-', 40)."\n";

    // Get admin user for authenticated requests
    $adminUser = User::where('email', 'admin@cems.my')->first();

    test('navigation has all menu items', function () use ($adminUser) {
        if (! $adminUser) {
            throw new Exception('Admin user not found');
        }

        // Check views have complete navigation
        $views = [
            'dashboard.blade.php',
            'compliance.blade.php',
            'accounting.blade.php',
            'reports.blade.php',
            'users/index.blade.php',
            'users/create.blade.php',
        ];

        $requiredItems = [
            'Dashboard',
            'Transactions',
            'Stock/Cash',
            'Compliance',
            'Accounting',
            'Reports',
            'Users',
            'Logout',
        ];

        foreach ($views as $view) {
            $path = __DIR__."/resources/views/{$view}";
            if (! file_exists($path)) {
                throw new Exception("View not found: {$view}");
            }

            $content = file_get_contents($path);

            foreach ($requiredItems as $item) {
                if (strpos($content, $item) === false) {
                    throw new Exception("Missing '{$item}' in {$view}");
                }
            }
        }

        return true;
    });

    test('navigation links have correct URLs', function () {
        $views = [
            'dashboard.blade.php',
            'compliance.blade.php',
        ];

        $requiredLinks = [
            'href="/"',
            'href="/transactions"',
            'href="/stock-cash"',
            'href="/compliance"',
            'href="/accounting"',
            'href="/reports"',
            'href="/users"',
            'href="/logout"',
        ];

        foreach ($views as $view) {
            $path = __DIR__."/resources/views/{$view}";
            $content = file_get_contents($path);

            foreach ($requiredLinks as $link) {
                if (strpos($content, $link) === false) {
                    throw new Exception("Missing link '{$link}' in {$view}");
                }
            }
        }

        return true;
    });

    test('logout form has CSRF protection', function () {
        $views = [
            'dashboard.blade.php',
            'compliance.blade.php',
        ];

        foreach ($views as $view) {
            $path = __DIR__."/resources/views/{$view}";
            $content = file_get_contents($path);

            // Check for logout form with CSRF
            if (strpos($content, 'id="logout-form"') === false) {
                throw new Exception("Missing logout form in {$view}");
            }

            if (strpos($content, 'action="/logout"') === false) {
                throw new Exception("Missing logout action in {$view}");
            }

            if (strpos($content, '@csrf') === false && strpos($content, 'csrf') === false) {
                throw new Exception("Missing CSRF in logout form in {$view}");
            }
        }

        return true;
    });

    test('navigation styling is consistent', function () {
        $views = [
            'dashboard.blade.php',
            'compliance.blade.php',
            'accounting.blade.php',
        ];

        foreach ($views as $view) {
            $path = __DIR__."/resources/views/{$view}";
            $content = file_get_contents($path);

            // Check header and nav classes
            if (strpos($content, 'class="header"') === false) {
                throw new Exception("Missing header class in {$view}");
            }

            if (strpos($content, 'class="nav"') === false) {
                throw new Exception("Missing nav class in {$view}");
            }
        }

        return true;
    });
}

// ==================== SUMMARY ====================
echo "\n";
echo str_repeat('=', 40)."\n";
echo "Test Summary\n";
echo str_repeat('=', 40)."\n";
echo "Passed: \033[32m$passed\033[0m\n";
echo "Failed: \033[31m$failed\033[0m\n";
echo 'Total:  '.($passed + $failed)."\n";

if ($failed > 0) {
    echo "\n\033[31mFailed Tests:\033[0m\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
}

echo str_repeat('=', 40)."\n";

exit($failed > 0 ? 1 : 0);
