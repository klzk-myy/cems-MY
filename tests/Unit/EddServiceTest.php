<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\FlaggedTransaction;
use App\Services\ComplianceService;
use App\Services\EddService;
use App\Services\MathService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EddServiceTest extends TestCase
{
    use RefreshDatabase;

    protected EddService $eddService;

    protected ComplianceService $complianceService;

    protected MathService $mathService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mathService = new MathService;
        $this->complianceService = resolve(ComplianceService::class);
        $this->eddService = new EddService($this->mathService, $this->complianceService);
    }

    public function test_edd_record_complete_requires_all_documents(): void
    {
        // Create a high-risk customer (triggers Enhanced CDD document requirements)
        $customer = Customer::factory()->create([
            'risk_rating' => 'High',
        ]);

        // Create EDD record with High risk level
        $flag = FlaggedTransaction::factory()->create(['customer_id' => $customer->id]);
        $eddRecord = $this->eddService->createEddRecord($flag, ['risk_level' => 'High']);

        // Set source of funds and purpose (these alone would pass the old check)
        $eddRecord->update([
            'source_of_funds' => 'Employment income',
            'purpose_of_transaction' => 'Investment',
        ]);

        // Should NOT be complete - missing all Enhanced CDD documents
        $this->assertFalse($this->eddService->isRecordComplete($eddRecord->fresh()));

        // Add MyKad (1 of 3 required for Enhanced)
        CustomerDocument::factory()->create([
            'customer_id' => $customer->id,
            'document_type' => 'MyKad',
            'file_path' => '/path/to/mykad.jpg',
            'verified_by' => null,
            'verified_at' => null,
        ]);

        // Should NOT be complete - still missing Proof_of_Address and Passport
        $this->assertFalse($this->eddService->isRecordComplete($eddRecord->fresh()));

        // Add Proof_of_Address
        CustomerDocument::factory()->create([
            'customer_id' => $customer->id,
            'document_type' => 'Proof_of_Address',
            'file_path' => '/path/to/address.jpg',
            'verified_by' => null,
            'verified_at' => null,
        ]);

        // Should NOT be complete - still missing Passport
        $this->assertFalse($this->eddService->isRecordComplete($eddRecord->fresh()));

        // Add Passport
        CustomerDocument::factory()->create([
            'customer_id' => $customer->id,
            'document_type' => 'Passport',
            'file_path' => '/path/to/passport.jpg',
            'verified_by' => null,
            'verified_at' => null,
        ]);

        // For Enhanced CDD, documents must be verified (not just uploaded)
        // So this should NOT be complete yet
        $this->assertFalse($this->eddService->isRecordComplete($eddRecord->fresh()));

        // Verify all documents
        $documents = CustomerDocument::where('customer_id', $customer->id)->get();
        foreach ($documents as $doc) {
            $doc->update([
                'verified_by' => 1,
                'verified_at' => now(),
            ]);
        }

        // Should NOW be complete - all documents uploaded AND verified
        $this->assertTrue($this->eddService->isRecordComplete($eddRecord->fresh()));
    }

    public function test_edd_record_complete_medium_risk_does_not_require_documents(): void
    {
        // Create a medium-risk customer
        $customer = Customer::factory()->create([
            'risk_rating' => 'Medium',
        ]);

        $flag = FlaggedTransaction::factory()->create(['customer_id' => $customer->id]);
        $eddRecord = $this->eddService->createEddRecord($flag, ['risk_level' => 'Medium']);

        // Set source of funds and purpose
        $eddRecord->update([
            'source_of_funds' => 'Employment income',
            'purpose_of_transaction' => 'Investment',
        ]);

        // Should be complete even without documents for Medium risk
        // (only High risk requires Enhanced CDD document check)
        $this->assertTrue($this->eddService->isRecordComplete($eddRecord->fresh()));
    }

    public function test_edd_record_complete_with_empty_source_or_purpose(): void
    {
        $customer = Customer::factory()->create([
            'risk_rating' => 'High',
        ]);

        $flag = FlaggedTransaction::factory()->create(['customer_id' => $customer->id]);
        $eddRecord = $this->eddService->createEddRecord($flag, ['risk_level' => 'High']);

        // Add all documents
        foreach (['MyKad', 'Proof_of_Address', 'Passport'] as $docType) {
            $doc = CustomerDocument::factory()->create([
                'customer_id' => $customer->id,
                'document_type' => $docType,
                'file_path' => '/path/to/doc.jpg',
            ]);
        }

        // Verify all documents so they are compliant
        $documents = CustomerDocument::where('customer_id', $customer->id)->get();
        foreach ($documents as $doc) {
            $doc->update([
                'verified_by' => 1,
                'verified_at' => now(),
            ]);
        }

        // Missing source_of_funds (purpose is set)
        $eddRecord->update([
            'purpose_of_transaction' => 'Investment',
        ]);

        $this->assertFalse($this->eddService->isRecordComplete($eddRecord->fresh()));

        // Add source_of_funds (now both source and purpose are set)
        $eddRecord->update([
            'source_of_funds' => 'Employment income',
        ]);

        // Now should be complete (source and purpose set, documents verified)
        $this->assertTrue($this->eddService->isRecordComplete($eddRecord->fresh()));

        // Clear purpose - should no longer be complete
        $eddRecord->update([
            'purpose_of_transaction' => null,
        ]);

        $this->assertFalse($this->eddService->isRecordComplete($eddRecord->fresh()));

        // Add purpose back
        $eddRecord->update([
            'purpose_of_transaction' => 'Investment',
        ]);

        // Now should be complete again
        $this->assertTrue($this->eddService->isRecordComplete($eddRecord->fresh()));
    }
}
