<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CustomerDocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_document_actually_stores_file(): void
    {
        $user = User::factory()->create(['role' => UserRole::Teller]);
        $customer = Customer::factory()->create();

        $this->actingAs($user);

        $file = UploadedFile::fake()->create('kyc-doc.pdf', 100, 'application/pdf');

        $response = $this->postJson("/api/v1/customers/{$customer->id}/kyc", [
            'document' => $file,
            'document_type' => 'MyKad',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);
        $this->assertDatabaseHas('customer_documents', [
            'customer_id' => $customer->id,
        ]);
    }
}
