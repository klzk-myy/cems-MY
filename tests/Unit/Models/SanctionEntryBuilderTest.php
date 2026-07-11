<?php

namespace Tests\Unit\Models;

use App\Enums\SanctionStatus;
use App\Models\SanctionEntry;
use PHPUnit\Framework\TestCase;

class SanctionEntryBuilderTest extends TestCase
{
    public function test_build_for_create_includes_create_only_fields(): void
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

        $payload = SanctionEntry::buildForCreate($data, $normalized);

        $this->assertSame(1, $payload['list_id']);
        $this->assertSame('1990-01-01', $payload['date_of_birth']);
        $this->assertSame(SanctionStatus::Active->value, $payload['status']);
        $this->assertArrayNotHasKey('list_source', $payload);
        $this->assertArrayNotHasKey('address', $payload);
    }

    public function test_build_for_create_status_uses_active_enum_value(): void
    {
        $data = [
            'list_id' => 1,
            'entity_name' => 'John Doe',
        ];

        $normalized = [
            'normalized_name' => 'john doe',
            'soundex_code' => 'J530',
            'metaphone_code' => 'JN T',
        ];

        $payload = SanctionEntry::buildForCreate($data, $normalized);

        $this->assertSame(SanctionStatus::Active->value, $payload['status']);
    }

    public function test_build_for_update_includes_update_only_fields(): void
    {
        $data = [
            'entity_name' => 'John Doe',
            'entity_type' => 'Individual',
            'list_source' => 'OFAC',
            'address' => '123 Main St',
            'city' => 'New York',
            'country' => 'US',
            'postal_code' => '10001',
            'date_of_birth' => '1990-01-01',
            'status' => 'inactive',
        ];

        $normalized = [
            'normalized_name' => 'john doe',
            'soundex_code' => 'J530',
            'metaphone_code' => 'JN T',
        ];

        $payload = SanctionEntry::buildForUpdate($data, $normalized);

        $this->assertSame('OFAC', $payload['list_source']);
        $this->assertSame('123 Main St', $payload['address']);
        $this->assertSame('New York', $payload['city']);
        $this->assertSame('US', $payload['country']);
        $this->assertSame('10001', $payload['postal_code']);
        $this->assertSame('1990-01-01', $payload['date_of_birth']);
        $this->assertSame('inactive', $payload['status']);
        $this->assertArrayNotHasKey('list_id', $payload);
    }

    public function test_build_for_update_allows_missing_entity_name_and_entity_type(): void
    {
        $data = [
            'list_source' => 'OFAC',
        ];

        $normalized = [
            'normalized_name' => 'john doe',
            'soundex_code' => 'J530',
            'metaphone_code' => 'JN T',
        ];

        $payload = SanctionEntry::buildForUpdate($data, $normalized);

        $this->assertNull($payload['entity_name']);
        $this->assertNull($payload['entity_type']);
        $this->assertSame('OFAC', $payload['list_source']);
    }

    public function test_build_for_update_preserves_provided_normalized_values(): void
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

        $payload = SanctionEntry::buildForUpdate($data, $normalized);

        $this->assertSame('jane doe', $payload['normalized_name']);
        $this->assertSame('J530', $payload['soundex_code']);
        $this->assertSame('JN T', $payload['metaphone_code']);
    }

    public function test_build_for_update_includes_status_when_provided(): void
    {
        $data = [
            'entity_name' => 'John Doe',
            'status' => 'inactive',
        ];

        $normalized = [
            'normalized_name' => 'john doe',
            'soundex_code' => 'J530',
            'metaphone_code' => 'JN T',
        ];

        $payload = SanctionEntry::buildForUpdate($data, $normalized);

        $this->assertSame('inactive', $payload['status']);
    }

    public function test_build_for_update_omits_status_when_absent(): void
    {
        $data = [
            'entity_name' => 'John Doe',
        ];

        $normalized = [
            'normalized_name' => 'john doe',
            'soundex_code' => 'J530',
            'metaphone_code' => 'JN T',
        ];

        $payload = SanctionEntry::buildForUpdate($data, $normalized);

        $this->assertArrayNotHasKey('status', $payload);
    }

    public function test_build_for_create_default_nulls_are_handled(): void
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

        $payload = SanctionEntry::buildForCreate($data, $normalized);

        $this->assertNull($payload['aliases']);
        $this->assertNull($payload['nationality']);
        $this->assertNull($payload['reference_number']);
        $this->assertNull($payload['listing_date']);
        $this->assertNull($payload['details']);
    }
}
