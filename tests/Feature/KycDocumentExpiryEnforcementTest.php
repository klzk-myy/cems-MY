<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Counter;
use App\Models\CustomerDocument;
use App\Models\User;
use App\Services\Compliance\KycDocumentExpiryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Tests\TestCase;

class KycDocumentExpiryEnforcementTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    protected function createTeller(): User
    {
        return User::factory()->create(['role' => UserRole::Teller]);
    }

    /**
     * @return TestResponse<RedirectResponse>
     */
    protected function postTransaction(User $teller, Counter $counter, string $type, int $customerId): TestResponse
    {
        // validateTillBalance rejects tills outside the authenticated
        // user's branch, so align the teller with the counter's branch.
        $teller->branch_id = $counter->branch_id;
        $teller->save();

        $this->actingAs($teller)->withSession([
            'mfa_verified' => true,
            'mfa_verified_at' => now()->timestamp,
        ]);

        return $this->post('/transactions', [
            'type' => $type,
            'currency_code' => 'USD',
            'amount_foreign' => '100.00',
            'rate' => '4.50',
            'customer_id' => $customerId,
            'purpose' => 'Travel',
            'source_of_funds' => 'Savings',
            'branch_id' => $counter->branch_id,
            'counter_id' => $counter->id,
            'idempotency_key' => uniqid('test_', true),
        ]);
    }

    #[Test]
    public function customer_without_documents_can_transact(): void
    {
        $teller = $this->createTeller();
        $customer = $this->createTestCustomer();
        $counter = $this->setupOpenTill($teller, 'USD');

        $response = $this->postTransaction($teller, $counter, TransactionType::Buy->value, (int) $customer->id);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertDatabaseHas('transactions', [
            'customer_id' => $customer->id,
            'amount_foreign' => '100.00',
        ]);
    }

    #[Test]
    public function customer_with_valid_identity_document_can_transact(): void
    {
        $teller = $this->createTeller();
        $customer = $this->createTestCustomer();
        $counter = $this->setupOpenTill($teller, 'USD');

        CustomerDocument::factory()->create([
            'customer_id' => $customer->id,
            'document_type' => 'MyKad',
            'expiry_date' => now()->addYear(),
            'verified_by' => $teller->id,
            'verified_at' => now(),
        ]);

        $response = $this->postTransaction($teller, $counter, TransactionType::Buy->value, (int) $customer->id);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertDatabaseHas('transactions', [
            'customer_id' => $customer->id,
            'amount_foreign' => '100.00',
        ]);
    }

    #[Test]
    public function customer_with_all_expired_identity_documents_cannot_buy(): void
    {
        $teller = $this->createTeller();
        $customer = $this->createTestCustomer();
        $counter = $this->setupOpenTill($teller, 'USD');

        CustomerDocument::factory()->create([
            'customer_id' => $customer->id,
            'document_type' => 'MyKad',
            'expiry_date' => now()->subDays(10),
            'verified_by' => $teller->id,
            'verified_at' => now(),
        ]);

        $response = $this->postTransaction($teller, $counter, TransactionType::Buy->value, (int) $customer->id);

        $response->assertSessionHasErrors('customer_id');
        $this->assertDatabaseMissing('transactions', [
            'customer_id' => $customer->id,
            'amount_foreign' => '100.00',
        ]);
    }

    #[Test]
    public function customer_with_all_expired_identity_documents_cannot_sell(): void
    {
        $teller = $this->createTeller();
        $customer = $this->createTestCustomer();
        $counter = $this->setupOpenTill($teller, 'USD', '1000.00');

        CustomerDocument::factory()->create([
            'customer_id' => $customer->id,
            'document_type' => 'MyKad',
            'expiry_date' => now()->subDays(10),
            'verified_by' => $teller->id,
            'verified_at' => now(),
        ]);

        $response = $this->postTransaction($teller, $counter, TransactionType::Sell->value, (int) $customer->id);

        $response->assertSessionHasErrors('customer_id');
        $this->assertDatabaseMissing('transactions', [
            'customer_id' => $customer->id,
            'amount_foreign' => '100.00',
        ]);
    }

    #[Test]
    public function unexpired_document_within_grace_period_does_not_block(): void
    {
        $teller = $this->createTeller();
        $customer = $this->createTestCustomer();
        $counter = $this->setupOpenTill($teller, 'USD');

        CustomerDocument::factory()->create([
            'customer_id' => $customer->id,
            'document_type' => 'MyKad',
            'expiry_date' => now()->subDays(2),
            'verified_by' => $teller->id,
            'verified_at' => now(),
        ]);

        $service = app(KycDocumentExpiryService::class);

        $this->assertFalse($service->hasAllIdentityDocumentsExpired($customer));
    }

    #[Test]
    public function expire_documents_marks_past_expiry_verified_documents_as_expired(): void
    {
        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $customer = $this->createTestCustomer();

        $expired = CustomerDocument::factory()->create([
            'customer_id' => $customer->id,
            'document_type' => 'MyKad',
            'expiry_date' => now()->subDays(30),
            'status' => 'verified',
            'verified_by' => $user->id,
            'verified_at' => now(),
        ]);

        $unexpired = CustomerDocument::factory()->create([
            'customer_id' => $customer->id,
            'document_type' => 'Passport',
            'expiry_date' => now()->addYear(),
            'status' => 'verified',
            'verified_by' => $user->id,
            'verified_at' => now(),
        ]);

        $count = app(KycDocumentExpiryService::class)->expireDocuments();

        $this->assertGreaterThanOrEqual(1, $count);
        $this->assertDatabaseHas('customer_documents', ['id' => $expired->id, 'status' => 'expired']);
        $this->assertDatabaseHas('customer_documents', ['id' => $unexpired->id, 'status' => 'verified']);
    }

    #[Test]
    public function compliance_user_can_reject_document_and_reason_is_persisted(): void
    {
        $compliance = User::factory()->create([
            'role' => UserRole::ComplianceOfficer,
            'branch_id' => null,
        ]);
        $customer = $this->createTestCustomer();
        $document = CustomerDocument::factory()->create([
            'customer_id' => $customer->id,
            'uploaded_by' => $compliance->id,
        ]);

        $response = $this->actingAs($compliance)->post("/kyc-documents/{$document->id}/reject", [
            'reason' => 'Document is blurred and unreadable',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('customer_documents', [
            'id' => $document->id,
            'status' => 'rejected',
            'rejection_reason' => 'Document is blurred and unreadable',
        ]);
    }

    #[Test]
    public function reject_requires_a_reason(): void
    {
        $compliance = User::factory()->create([
            'role' => UserRole::ComplianceOfficer,
            'branch_id' => null,
        ]);
        $customer = $this->createTestCustomer();
        $document = CustomerDocument::factory()->create([
            'customer_id' => $customer->id,
            'uploaded_by' => $compliance->id,
        ]);

        $response = $this->actingAs($compliance)->postJson("/kyc-documents/{$document->id}/reject", []);

        $response->assertStatus(422);
    }

    #[Test]
    public function teller_cannot_verify_document(): void
    {
        $teller = $this->createTeller();
        $customer = $this->createTestCustomer();
        $document = CustomerDocument::factory()->create([
            'customer_id' => $customer->id,
            'uploaded_by' => $teller->id,
        ]);

        $response = $this->actingAs($teller)->post("/kyc-documents/{$document->id}/verify");

        $response->assertStatus(403);
        $this->assertDatabaseHas('customer_documents', ['id' => $document->id, 'status' => 'pending']);
    }

    #[Test]
    public function compliance_user_can_verify_document(): void
    {
        $compliance = User::factory()->create([
            'role' => UserRole::ComplianceOfficer,
            'branch_id' => null,
        ]);
        $customer = $this->createTestCustomer();
        $document = CustomerDocument::factory()->create([
            'customer_id' => $customer->id,
            'uploaded_by' => $compliance->id,
            'rejection_reason' => 'Blurry copy',
        ]);

        $response = $this->actingAs($compliance)->post("/kyc-documents/{$document->id}/verify");

        $response->assertOk();
        $this->assertDatabaseHas('customer_documents', [
            'id' => $document->id,
            'status' => 'verified',
            'verified_by' => $compliance->id,
            'rejection_reason' => null,
        ]);
    }

    #[Test]
    public function customer_profile_renders_kyc_documents_panel(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $customer = $this->createTestCustomer();

        CustomerDocument::factory()->create([
            'customer_id' => $customer->id,
            'document_type' => 'MyKad',
            'status' => 'verified',
            'expiry_date' => now()->addYear(),
            'uploaded_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get("/customers/{$customer->id}");

        $response->assertOk();
        $response->assertSee('KYC Documents');
        $response->assertSee('MyKad');
        $response->assertSee('Verified');
    }

    #[Test]
    public function authorized_user_can_download_document(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('documents/kyc/test-document.jpg', 'fake-image-content');

        $compliance = User::factory()->create([
            'role' => UserRole::ComplianceOfficer,
            'branch_id' => null,
        ]);
        $customer = $this->createTestCustomer();
        $document = CustomerDocument::factory()->create([
            'customer_id' => $customer->id,
            'file_path' => 'kyc/test-document.jpg',
            'uploaded_by' => $compliance->id,
        ]);

        $response = $this->actingAs($compliance)->get("/kyc-documents/{$document->id}/download");

        $response->assertOk();
    }

    #[Test]
    public function download_rejects_path_traversal_payloads(): void
    {
        $compliance = User::factory()->create([
            'role' => UserRole::ComplianceOfficer,
            'branch_id' => null,
        ]);
        $customer = $this->createTestCustomer();
        $document = CustomerDocument::factory()->create([
            'customer_id' => $customer->id,
            'file_path' => '../../.env',
            'uploaded_by' => $compliance->id,
        ]);

        $response = $this->actingAs($compliance)->get("/kyc-documents/{$document->id}/download");

        $response->assertNotFound();
    }
}
