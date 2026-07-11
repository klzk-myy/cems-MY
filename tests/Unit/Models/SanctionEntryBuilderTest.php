<?php

namespace Tests\Unit\Models;

use App\Models\SanctionEntry;
use PHPUnit\Framework\TestCase;

class SanctionEntryBuilderTest extends TestCase
{
    public function test_create_payload_includes_create_only_fields(): void
    {
        $data = [
            'list_id' => 1,
            'entity_name' => 'John Doe',
            'entity_type' => 'Individual',
            'date_of_birth' => '1990-01-01',
        ];

        $normalized = [
            'normalized_name' => 'john doe',
            'soundex_code' => 'J530',
            'metaphone_code' => 'JN T',
        ];

        $payload = SanctionEntry::buildFromValidated($data, $normalized);

        $this->assertSame(1, $payload['list_id']);
        $this->assertSame('1990-01-01', $payload['date_of_birth']);
        $this->assertSame('active', $payload['status']);
        $this->assertArrayNotHasKey('list_source', $payload);
        $this->assertArrayNotHasKey('address', $payload);
    }

    public function test_update_payload_includes_update_only_fields(): void
    {
        $data = [
            'entity_name' => 'John Doe',
            'entity_type' => 'Individual',
            'list_source' => 'OFAC',
            'address' => '123 Main St',
            'city' => 'New York',
            'country' => 'US',
            'postal_code' => '10001',
        ];

        $normalized = [
            'normalized_name' => 'john doe',
            'soundex_code' => 'J530',
            'metaphone_code' => 'JN T',
        ];

        $payload = SanctionEntry::buildFromValidated($data, $normalized, true);

        $this->assertSame('OFAC', $payload['list_source']);
        $this->assertSame('123 Main St', $payload['address']);
        $this->assertSame('New York', $payload['city']);
        $this->assertSame('US', $payload['country']);
        $this->assertSame('10001', $payload['postal_code']);
        $this->assertArrayNotHasKey('list_id', $payload);
        $this->assertArrayNotHasKey('date_of_birth', $payload);
        $this->assertArrayNotHasKey('status', $payload);
    }

    public function test_update_payload_preserves_provided_normalized_values(): void
    {
        $data = [
            'entity_name' => 'Jane Doe',
            'entity_type' => 'Individual',
        ];

        $normalized = [
            'normalized_name' => 'jane doe',
            'soundex_code' => 'J530',
            'metaphone_code' => 'JN T',
        ];

        $payload = SanctionEntry::buildFromValidated($data, $normalized, true);

        $this->assertSame('jane doe', $payload['normalized_name']);
        $this->assertSame('J530', $payload['soundex_code']);
        $this->assertSame('JN T', $payload['metaphone_code']);
    }

    public function test_default_nulls_are_handled(): void
    {
        $data = [
            'list_id' => 1,
            'entity_name' => 'John Doe',
            'entity_type' => 'Individual',
        ];

        $normalized = [
            'normalized_name' => 'john doe',
            'soundex_code' => 'J530',
            'metaphone_code' => 'JN T',
        ];

        $payload = SanctionEntry::buildFromValidated($data, $normalized);

        $this->assertNull($payload['aliases']);
        $this->assertNull($payload['nationality']);
        $this->assertNull($payload['reference_number']);
        $this->assertNull($payload['listing_date']);
        $this->assertNull($payload['details']);
    }
}
