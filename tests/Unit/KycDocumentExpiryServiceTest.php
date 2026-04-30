<?php

namespace Tests\Unit;

use App\Enums\CddLevel;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\User;
use App\Services\AuditService;
use App\Services\KycDocumentExpiryService;
use App\Services\ThresholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KycDocumentExpiryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected KycDocumentExpiryService $service;

    protected ThresholdService $thresholdService;

    protected Branch $branch;

    protected Counter $counter;

    protected Customer $customer;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->thresholdService = new ThresholdService;
        $this->service = new KycDocumentExpiryService(
            $this->thresholdService,
            app(AuditService::class)
        );

        $this->setupTestData();
    }

    protected function setupTestData(): void
    {
        $this->branch = Branch::factory()->create([
            'code' => 'HQ-TEST',
            'name' => 'Test Head Office',
            'address' => '123 Test Street',
            'phone' => '+60312345678',
            'email' => 'test@localhost.com',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'username' => 'testuser',
            'email' => 'test@localhost.com',
            'password_hash' => bcrypt('password'),
            'role' => UserRole::Teller,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $this->counter = Counter::factory()->create([
            'name' => 'Test Counter',
            'code' => 'CTR-TEST',
            'branch_id' => $this->branch->id,
        ]);

        $this->customer = Customer::factory()->create([
            'full_name' => 'Test Customer',
            'id_type' => 'MyKad',
            'id_number_encrypted' => encrypt('123456789012'),
            'nationality' => 'MY',
            'date_of_birth' => '1990-01-15',
            'risk_rating' => 'Low',
            'cdd_level' => CddLevel::Simplified,
            'is_active' => true,
        ]);
    }

    public function test_no_block_when_all_documents_valid(): void
    {
        CustomerDocument::factory()->create([
            'customer_id' => $this->customer->id,
            'document_type' => 'MyKad',
            'file_path' => '/path/to/myKad.jpg',
            'file_hash' => 'abc123',
            'uploaded_by' => $this->user->id,
            'expiry_date' => now()->addYear(),
            'verified_by' => $this->user->id,
            'verified_at' => now(),
        ]);

        $this->assertFalse($this->service->mustBlockDueToExpiredDocuments($this->customer));
    }

    public function test_no_block_within_grace_period(): void
    {
        CustomerDocument::factory()->create([
            'customer_id' => $this->customer->id,
            'document_type' => 'MyKad',
            'file_path' => '/path/to/myKad.jpg',
            'file_hash' => 'abc123',
            'uploaded_by' => $this->user->id,
            'expiry_date' => now()->subDays(3),
            'verified_by' => $this->user->id,
            'verified_at' => now(),
        ]);

        $this->assertFalse($this->service->mustBlockDueToExpiredDocuments($this->customer));
    }

    public function test_blocks_after_grace_period_expired(): void
    {
        CustomerDocument::factory()->create([
            'customer_id' => $this->customer->id,
            'document_type' => 'MyKad',
            'file_path' => '/path/to/myKad.jpg',
            'file_hash' => 'abc123',
            'uploaded_by' => $this->user->id,
            'expiry_date' => now()->subDays(10),
            'verified_by' => $this->user->id,
            'verified_at' => now(),
        ]);

        $this->assertTrue($this->service->mustBlockDueToExpiredDocuments($this->customer));
    }

    public function test_blocks_when_document_missing(): void
    {
        $this->assertTrue($this->service->mustBlockDueToExpiredDocuments($this->customer));
    }

    public function test_standard_cdd_requires_proof_of_address(): void
    {
        $this->customer->update(['cdd_level' => CddLevel::Standard]);

        CustomerDocument::factory()->create([
            'customer_id' => $this->customer->id,
            'document_type' => 'MyKad',
            'file_path' => '/path/to/myKad.jpg',
            'file_hash' => 'abc123',
            'uploaded_by' => $this->user->id,
            'expiry_date' => now()->addYear(),
            'verified_by' => $this->user->id,
            'verified_at' => now(),
        ]);

        $this->assertTrue($this->service->mustBlockDueToExpiredDocuments($this->customer));
    }

    public function test_standard_cdd_no_block_when_all_documents_present(): void
    {
        $this->customer->update(['cdd_level' => CddLevel::Standard]);

        CustomerDocument::factory()->create([
            'customer_id' => $this->customer->id,
            'document_type' => 'MyKad',
            'file_path' => '/path/to/myKad.jpg',
            'file_hash' => 'abc123',
            'uploaded_by' => $this->user->id,
            'expiry_date' => now()->addYear(),
            'verified_by' => $this->user->id,
            'verified_at' => now(),
        ]);

        CustomerDocument::factory()->create([
            'customer_id' => $this->customer->id,
            'document_type' => 'Proof_of_Address',
            'file_path' => '/path/to/poa.jpg',
            'file_hash' => 'def456',
            'uploaded_by' => $this->user->id,
            'expiry_date' => now()->addYear(),
            'verified_by' => $this->user->id,
            'verified_at' => now(),
        ]);

        $this->assertFalse($this->service->mustBlockDueToExpiredDocuments($this->customer));
    }

    public function test_enhanced_cdd_requires_passport(): void
    {
        $this->customer->update(['cdd_level' => CddLevel::Enhanced]);

        CustomerDocument::factory()->create([
            'customer_id' => $this->customer->id,
            'document_type' => 'MyKad',
            'file_path' => '/path/to/myKad.jpg',
            'file_hash' => 'abc123',
            'uploaded_by' => $this->user->id,
            'expiry_date' => now()->addYear(),
            'verified_by' => $this->user->id,
            'verified_at' => now(),
        ]);

        CustomerDocument::factory()->create([
            'customer_id' => $this->customer->id,
            'document_type' => 'Proof_of_Address',
            'file_path' => '/path/to/poa.jpg',
            'file_hash' => 'def456',
            'uploaded_by' => $this->user->id,
            'expiry_date' => now()->addYear(),
            'verified_by' => $this->user->id,
            'verified_at' => now(),
        ]);

        $this->assertTrue($this->service->mustBlockDueToExpiredDocuments($this->customer));
    }

    public function test_enhanced_cdd_no_block_when_all_documents_present(): void
    {
        $this->customer->update(['cdd_level' => CddLevel::Enhanced]);

        CustomerDocument::factory()->create([
            'customer_id' => $this->customer->id,
            'document_type' => 'MyKad',
            'file_path' => '/path/to/myKad.jpg',
            'file_hash' => 'abc123',
            'uploaded_by' => $this->user->id,
            'expiry_date' => now()->addYear(),
            'verified_by' => $this->user->id,
            'verified_at' => now(),
        ]);

        CustomerDocument::factory()->create([
            'customer_id' => $this->customer->id,
            'document_type' => 'Proof_of_Address',
            'file_path' => '/path/to/poa.jpg',
            'file_hash' => 'def456',
            'uploaded_by' => $this->user->id,
            'expiry_date' => now()->addYear(),
            'verified_by' => $this->user->id,
            'verified_at' => now(),
        ]);

        CustomerDocument::factory()->create([
            'customer_id' => $this->customer->id,
            'document_type' => 'Passport',
            'file_path' => '/path/to/passport.jpg',
            'file_hash' => 'ghi789',
            'uploaded_by' => $this->user->id,
            'expiry_date' => now()->addYear(),
            'verified_by' => $this->user->id,
            'verified_at' => now(),
        ]);

        $this->assertFalse($this->service->mustBlockDueToExpiredDocuments($this->customer));
    }

    public function test_get_expired_documents_returns_only_expired(): void
    {
        CustomerDocument::factory()->create([
            'customer_id' => $this->customer->id,
            'document_type' => 'MyKad',
            'file_path' => '/path/to/myKad.jpg',
            'file_hash' => 'abc123',
            'uploaded_by' => $this->user->id,
            'expiry_date' => now()->subDays(10),
            'verified_by' => $this->user->id,
            'verified_at' => now(),
        ]);

        CustomerDocument::factory()->create([
            'customer_id' => $this->customer->id,
            'document_type' => 'Proof_of_Address',
            'file_path' => '/path/to/poa.jpg',
            'file_hash' => 'def456',
            'uploaded_by' => $this->user->id,
            'expiry_date' => now()->addYear(),
            'verified_by' => $this->user->id,
            'verified_at' => now(),
        ]);

        $expired = $this->service->getExpiredDocuments($this->customer);

        $this->assertCount(1, $expired);
        $this->assertEquals('MyKad', $expired->first()->document_type);
    }

    public function test_no_grace_for_document_without_expiry_date(): void
    {
        CustomerDocument::factory()->create([
            'customer_id' => $this->customer->id,
            'document_type' => 'MyKad',
            'file_path' => '/path/to/myKad.jpg',
            'file_hash' => 'abc123',
            'uploaded_by' => $this->user->id,
            'expiry_date' => null,
            'verified_by' => $this->user->id,
            'verified_at' => now(),
        ]);

        $this->assertFalse($this->service->mustBlockDueToExpiredDocuments($this->customer));
    }
}
