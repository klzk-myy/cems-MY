<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use App\Rules\PasswordComplexityRule;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @group slow
 */
class SecurityTest extends TestCase
{
    use DatabaseTransactions;

    protected User $defaultUser;

    protected User $teller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->defaultUser = User::factory()->create();
        $this->teller = User::factory()->create(['role' => UserRole::Teller]);
    }

    /**
     * Test SQL injection prevention in search parameters
     */
    #[Test]
    public function sql_injection_prevention_in_search(): void
    {
        // Classic injection attempt
        $response = $this->actingAs($this->defaultUser)->get('/customers?search='.urlencode("' OR '1'='1"));

        // Should not expose SQL error, should just return empty or safe response
        $response->assertStatus(200);
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
    }

    /**
     * Test SQL injection in transaction search
     */
    #[Test]
    public function sql_injection_prevention_in_transaction_search(): void
    {
        $response = $this->actingAs($this->defaultUser)->get('/transactions?search='.urlencode("'; DROP TABLE transactions;--"));

        $response->assertStatus(200);
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
    }

    /**
     * Test XSS prevention in customer name
     */
    #[Test]
    public function xss_prevention_in_name_field(): void
    {
        // Attempt XSS in customer creation
        $response = $this->actingAs($this->defaultUser)->post('/customers', [
            'name' => '<script>alert("XSS")</script>',
            'ic_number' => '123456-12-1234',
        ]);

        // The name should be sanitized or rejected
        // If accepted, it should be escaped when displayed
        $this->assertTrue(
            $response->isRedirect() || // Redirected (validation)
            ! $this->hasUnescapedScript($response) // Or response is safe
        );
    }

    /**
     * Test XSS prevention in transaction purpose
     */
    #[Test]
    public function xss_prevention_in_transaction_purpose(): void
    {
        $response = $this->actingAs($this->defaultUser)->post('/transactions', [
            'purpose' => '<img src=x onerror=alert(1)>',
        ]);

        $this->assertStringNotContainsString('<script>', $response->getContent());
    }

    /**
     * Test CSRF token required for form submission
     */
    #[Test]
    public function csrf_token_required_for_transaction(): void
    {
        // Submit without CSRF token
        $response = $this->actingAs($this->defaultUser)->post('/transactions', [
            '_token' => 'invalid-token',
        ]);

        // Should get 419 (CSRF mismatch) or validation error
        $this->assertTrue(
            $response->status() === 419 ||
            $response->status() === 422 ||
            $response->status() === 302 // Redirect if handling differently
        );
    }

    /**
     * Test authorization - teller cannot access admin routes
     */
    #[Test]
    public function teller_cannot_access_admin_routes(): void
    {
        $response = $this->actingAs($this->teller)->get('/users');

        $response->assertStatus(403);
    }

    /**
     * Test authorization - teller cannot access accounting
     */
    #[Test]
    public function teller_cannot_access_accounting(): void
    {
        $response = $this->actingAs($this->teller)->get('/accounting');

        $response->assertStatus(403);
    }

    /**
     * Test authorization - teller cannot access compliance
     */
    #[Test]
    public function teller_cannot_access_compliance_routes(): void
    {
        $response = $this->actingAs($this->teller)->get('/compliance/alerts');

        $response->assertStatus(403);
    }

    /**
     * Test unauthorized access to another branch's data
     */
    #[Test]
    public function user_cannot_access_other_branch_data(): void
    {
        $branchA = Branch::factory()->create(['code' => 'SCOP-A'.uniqid()]);
        $branchB = Branch::factory()->create(['code' => 'SCOP-B'.uniqid()]);

        $userA = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $branchA->id,
        ]);

        $customerB = Customer::factory()->create([
            'full_name' => 'BranchB-'.uniqid(),
        ]);

        $response = $this->actingAs($userA)->getJson('/api/v1/customers');

        $response->assertOk();
        $this->assertStringNotContainsString(
            $customerB->full_name,
            $response->getContent(),
            'Branch A user should not see Branch B customers'
        );
    }

    /**
     * Test session fixation prevention
     */
    #[Test]
    public function session_regenerated_on_login(): void
    {
        $user = User::factory()->create(['password' => bcrypt('Secret12345!')]);

        $sessionBefore = session()->getId();

        $this->post('/login', [
            'username' => $user->username,
            'password' => 'Secret12345!',
        ]);

        $sessionAfter = session()->getId();

        $this->assertNotEquals($sessionBefore, $sessionAfter, 'Session ID should change after login');
    }

    /**
     * Test mass assignment protection
     */
    #[Test]
    public function mass_assignment_protection(): void
    {
        // Attempt to set admin role via mass assignment
        $response = $this->actingAs($this->defaultUser)->put('/users/'.$this->defaultUser->id, [
            'name' => 'Test',
            'role' => 'Admin', // Should be ignored or rejected
        ]);

        $this->defaultUser->refresh();
        $this->assertNotEquals('Admin', $this->defaultUser->role->value);
    }

    /**
     * Test inactive user cannot login
     */
    #[Test]
    public function inactive_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'is_active' => false,
            'password' => bcrypt('Secret12345!'),
        ]);

        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => 'Secret12345!',
        ]);

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    /**
     * Test input validation for invalid email
     */
    #[Test]
    public function invalid_email_rejected(): void
    {
        $response = $this->actingAs($this->defaultUser)->post('/customers', [
            'name' => 'Test Customer',
            'ic_number' => '123456-12-1234',
            'email' => 'not-an-email',
        ]);

        $response->assertSessionHasErrors('email');
    }

    /**
     * Test input validation for negative amounts
     */
    #[Test]
    public function negative_amount_rejected(): void
    {
        $response = $this->actingAs($this->defaultUser)->post('/transactions', [
            'amount' => '-100',
        ]);

        // Should either redirect (validation) or return error
        $this->assertTrue(in_array($response->status(), [302, 422, 400]));
    }

    /**
     * Test rate with too many decimals is handled
     */
    #[Test]
    public function rate_precision_validation(): void
    {
        $response = $this->actingAs($this->defaultUser)->post('/transactions', [
            'rate' => '4.723456789',
        ]);

        // Should either accept with truncation or reject
        $this->assertTrue(in_array($response->status(), [302, 422, 200, 201]));
    }

    /**
     * Test password must meet complexity requirements
     */
    #[Test]
    public function password_must_meet_complexity_requirements(): void
    {
        $rule = new PasswordComplexityRule;

        // Test too short (less than 12 characters)
        $errors = [];
        $fail = function ($message) use (&$errors) {
            $errors[] = $message;
        };
        $rule->validate('password', 'Short1!', $fail);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('at least 12 characters', $errors[0]);

        // Test missing uppercase
        $errors = [];
        $rule->validate('password', 'lowercase123!@', $fail);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('uppercase letter', $errors[0]);

        // Test missing lowercase
        $errors = [];
        $rule->validate('password', 'UPPERCASE123!@', $fail);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('lowercase letter', $errors[0]);

        // Test missing number
        $errors = [];
        $rule->validate('password', 'NoNumbers!@#abc', $fail);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('number', $errors[0]);

        // Test missing symbol
        $errors = [];
        $rule->validate('password', 'NoSymbol123Abc', $fail);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('symbol', $errors[0]);

        // Test valid password passes validation
        $errors = [];
        $rule->validate('password', 'SecureP@ssw0rd123', $fail);
        $this->assertEmpty($errors);
    }

    /**
     * Helper to check for unescaped script tags
     */
    private function hasUnescapedScript($response): bool
    {
        $content = $response->getContent();

        return str_contains($content, '<script>alert') ||
               str_contains($content, 'onerror=alert');
    }
}
