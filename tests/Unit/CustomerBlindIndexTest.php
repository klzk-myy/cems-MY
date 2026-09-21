<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\User;
use App\Services\Customer\CustomerService;
use App\Services\System\EncryptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerBlindIndexTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function blind_index_hash_is_deterministic(): void
    {
        $hash1 = CustomerService::computeBlindIndex('A123456');
        $hash2 = CustomerService::computeBlindIndex('A123456');

        $this->assertEquals($hash1, $hash2);
    }

    #[Test]
    public function blind_index_different_inputs_produce_different_hashes(): void
    {
        $hash1 = CustomerService::computeBlindIndex('A123456');
        $hash2 = CustomerService::computeBlindIndex('B123456');

        $this->assertNotEquals($hash1, $hash2);
    }

    #[Test]
    public function find_by_id_number_returns_correct_customer(): void
    {
        $plaintextId = 'A12345678';

        $customer = Customer::factory()->make([
            'full_name' => 'Test Customer',
            'id_type' => 'MyKad',
            'id_number_encrypted' => app(EncryptionService::class)->encrypt($plaintextId),
            'nationality' => 'MY',
            'date_of_birth' => '1990-01-01',
            'phone' => '60121234567',
            'pep_status' => false,
            'sanction_hit' => false,
            'risk_score' => 10,
            'risk_rating' => 'low',
            'cdd_level' => 'Simplified',
            'is_active' => true,
        ]);

        // Compute blind index manually since we can't rely on boot hook without save
        $customer->id_number_hash = CustomerService::computeBlindIndex($plaintextId);
        $customer->save();

        $customerService = app(CustomerService::class);
        $found = $customerService->findByIdNumber($plaintextId);

        $this->assertNotNull($found);
        $this->assertEquals($customer->id, $found->id);
    }

    #[Test]
    public function find_by_id_number_returns_null_for_non_existent(): void
    {
        $customerService = app(CustomerService::class);
        $found = $customerService->findByIdNumber('NONEXISTENT123');
        $this->assertNull($found);
    }

    #[Test]
    public function update_recomputes_blind_index_on_id_number_change(): void
    {
        $user = User::factory()->create();
        $service = app(CustomerService::class);

        $customer = $service->createCustomer([
            'full_name' => 'Blind Index Update',
            'id_type' => 'MyKad',
            'id_number' => 'OLDID123',
            'nationality' => 'MY',
            'date_of_birth' => '1990-01-01',
        ], $user->id);

        $service->updateCustomer($customer, ['id_number' => 'NEWID456'], $user->id);

        $customer->refresh();
        $this->assertSame(CustomerService::computeBlindIndex('NEWID456'), $customer->id_number_hash);
        $this->assertNull($service->findByIdNumber('OLDID123'));
        $this->assertSame($customer->id, $service->findByIdNumber('NEWID456')?->id);
    }

    #[Test]
    public function update_rejects_id_number_owned_by_another_customer(): void
    {
        $user = User::factory()->create();
        $service = app(CustomerService::class);

        $first = $service->createCustomer([
            'full_name' => 'First Holder',
            'id_type' => 'MyKad',
            'id_number' => 'SHARED111',
            'nationality' => 'MY',
            'date_of_birth' => '1990-01-01',
        ], $user->id);

        $second = $service->createCustomer([
            'full_name' => 'Second Holder',
            'id_type' => 'Passport',
            'id_number' => 'OTHER222',
            'nationality' => 'MY',
            'date_of_birth' => '1985-05-05',
        ], $user->id);

        try {
            $service->updateCustomer($second, ['id_number' => 'SHARED111'], $user->id);
            $this->fail('Expected ValidationException for duplicate id_number');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('id_number', $e->errors());
        }

        // The collision must not have persisted either the value or its hash.
        $second->refresh();
        $this->assertSame(CustomerService::computeBlindIndex('OTHER222'), $second->id_number_hash);
        $this->assertSame($first->id, $service->findByIdNumber('SHARED111')?->id);
    }
}
