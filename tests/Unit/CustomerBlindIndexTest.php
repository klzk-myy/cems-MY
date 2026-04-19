<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Services\EncryptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerBlindIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_blind_index_hash_is_deterministic(): void
    {
        $hash1 = Customer::computeBlindIndex('A123456');
        $hash2 = Customer::computeBlindIndex('A123456');

        $this->assertEquals($hash1, $hash2);
    }

    public function test_blind_index_different_inputs_produce_different_hashes(): void
    {
        $hash1 = Customer::computeBlindIndex('A123456');
        $hash2 = Customer::computeBlindIndex('B123456');

        $this->assertNotEquals($hash1, $hash2);
    }

    public function test_find_by_id_number_returns_correct_customer(): void
    {
        $plaintextId = 'A12345678';

        $customer = new Customer([
            'full_name' => 'Test Customer',
            'id_type' => 'MyKad',
            'id_number_encrypted' => app(EncryptionService::class)->encrypt($plaintextId),
            'nationality' => 'MY',
            'date_of_birth' => '1990-01-01',
            'phone' => '60121234567',
            'pep_status' => false,
            'sanction_hit' => false,
            'risk_score' => 10,
            'risk_rating' => 'Low',
            'cdd_level' => 'Simplified',
            'is_active' => true,
        ]);

        // Compute blind index manually since we can't rely on boot hook without save
        $customer->id_number_hash = Customer::computeBlindIndex($plaintextId);
        $customer->save();

        $found = Customer::findByIdNumber($plaintextId);

        $this->assertNotNull($found);
        $this->assertEquals($customer->id, $found->id);
    }

    public function test_find_by_id_number_returns_null_for_non_existent(): void
    {
        $found = Customer::findByIdNumber('NONEXISTENT123');
        $this->assertNull($found);
    }
}
