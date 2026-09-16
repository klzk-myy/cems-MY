<?php

namespace Tests\Unit;

use App\Enums\SanctionStatus;
use App\Models\SanctionEntry;
use App\Models\SanctionList;
use App\Services\Compliance\SanctionsImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SanctionsImportFormatsTest extends TestCase
{
    use RefreshDatabase;

    protected SanctionsImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SanctionsImportService::class);
    }

    #[Test]
    public function import_from_json_file_creates_entries(): void
    {
        $list = SanctionList::factory()->create([
            'source_url' => 'https://example.test/un.json',
            'slug' => 'un-json-test',
            'is_active' => true,
        ]);

        $path = tempnam(sys_get_temp_dir(), 'sanctions');
        file_put_contents($path, json_encode([
            'results' => [
                ['id' => 'js-001', 'name' => 'Jane Smith', 'entity_type' => 'Person'],
            ],
        ]));

        try {
            $result = $this->service->importFromJson($path, $list->id);
        } finally {
            @unlink($path);
        }

        $this->assertEquals(1, $result['created']);
        $this->assertEquals(1, $result['imported']);
        $this->assertTrue($result['new_entries_detected'] > 0);
        $this->assertSame('Jane Smith', SanctionEntry::first()->entity_name);
    }

    #[Test]
    public function import_from_un_xml_creates_entries(): void
    {
        $list = SanctionList::factory()->create([
            'slug' => 'un-xml-test',
            'is_active' => true,
        ]);

        $xml = <<<'XML'
<CONSOLIDATED_LIST>
  <INDIVIDUALS>
    <INDIVIDUAL dataid="un-001">
      <FIRST_NAME>John</FIRST_NAME>
      <SECOND_NAME>Doe</SECOND_NAME>
      <UN_LIST_TYPE>Individual</UN_LIST_TYPE>
      <REFERENCE_NUMBER>UN-001</REFERENCE_NUMBER>
      <NATIONALITY>US</NATIONALITY>
      <DATE_OF_BIRTH>1990-01-15</DATE_OF_BIRTH>
      <AKA><ALIAS_NAME>Johnny</ALIAS_NAME></AKA>
    </INDIVIDUAL>
  </INDIVIDUALS>
  <ENTITIES>
    <ENTITY dataid="un-002">
      <FIRST_NAME>Acme Corp</FIRST_NAME>
      <UN_LIST_TYPE>Entity</UN_LIST_TYPE>
      <REFERENCE_NUMBER>UN-002</REFERENCE_NUMBER>
    </ENTITY>
  </ENTITIES>
</CONSOLIDATED_LIST>
XML;

        $path = tempnam(sys_get_temp_dir(), 'sanctions');
        file_put_contents($path, $xml);

        try {
            $result = $this->service->importFromXml($path, $list->id, 'UNSCR');
        } finally {
            @unlink($path);
        }

        $this->assertEquals(2, $result['created']);

        $john = SanctionEntry::where('reference_number', 'un-001')->first();
        $this->assertNotNull($john);
        $this->assertSame('John Doe', $john->entity_name);
        $this->assertSame('US', $john->nationality);
        $this->assertSame('1990-01-15', $john->date_of_birth->format('Y-m-d'));
        $this->assertSame(['Johnny'], $john->aliases);

        $acme = SanctionEntry::where('reference_number', 'un-002')->first();
        $this->assertNotNull($acme);
        $this->assertSame('Acme Corp', $acme->entity_name);
    }

    #[Test]
    public function import_from_ofac_xml_creates_entries(): void
    {
        $list = SanctionList::factory()->create([
            'slug' => 'ofac-xml-test',
            'is_active' => true,
        ]);

        $xml = <<<'XML'
<sdnList>
  <sdnEntry uid="ofac-001">
    <lastName>SMITH</lastName>
    <firstName>JOHN</firstName>
    <sdnType>Individual</sdnType>
    <akaList>
      <aka>
        <firstName>JOHNNY</firstName>
        <lastName>SMITH</lastName>
      </aka>
    </akaList>
  </sdnEntry>
</sdnList>
XML;

        $path = tempnam(sys_get_temp_dir(), 'sanctions');
        file_put_contents($path, $xml);

        try {
            $result = $this->service->importFromXml($path, $list->id, 'OFAC');
        } finally {
            @unlink($path);
        }

        $this->assertEquals(1, $result['created']);

        $entry = SanctionEntry::where('reference_number', 'ofac-001')->first();
        $this->assertNotNull($entry);
        $this->assertSame('SMITH JOHN', $entry->entity_name);
        $this->assertSame(['JOHNNY SMITH'], $entry->aliases);
    }

    #[Test]
    public function import_from_eu_csv_creates_entries(): void
    {
        $list = SanctionList::factory()->create([
            'slug' => 'eu-csv-test',
            'is_active' => true,
        ]);

        $csv = "Name,Name Latin,Birth date,Nationality,Type of entity,Unique ID,Alias\n"
            .',Khalid Sheikh Mohammed,,YE,Legal person,EU-001,"KSM; Sheikh Khalid"'."\n";

        $path = tempnam(sys_get_temp_dir(), 'sanctions');
        file_put_contents($path, $csv);

        try {
            $result = $this->service->importFromCsv($path, $list->id, true);
        } finally {
            @unlink($path);
        }

        $this->assertEquals(1, $result['created']);

        $entry = SanctionEntry::where('reference_number', 'EU-001')->first();
        $this->assertNotNull($entry);
        $this->assertSame('Khalid Sheikh Mohammed', $entry->entity_name);
        $this->assertSame('YE', $entry->nationality);
        $this->assertSame(['KSM', 'Sheikh Khalid'], $entry->aliases);
    }

    #[Test]
    public function empty_import_does_not_deactivate_a_populated_list(): void
    {
        $list = SanctionList::factory()->create([
            'slug' => 'empty-guard-test',
            'is_active' => true,
        ]);

        $entry = SanctionEntry::factory()->create([
            'list_id' => $list->id,
            'reference_number' => 'E-001',
            'status' => 'active',
        ]);

        $path = tempnam(sys_get_temp_dir(), 'sanctions');
        file_put_contents($path, json_encode(['results' => []]));

        try {
            $this->service->importFromJson($path, $list->id);
            $this->fail('Expected RuntimeException for an empty import over a populated list');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('refusing to deactivate', $e->getMessage());
        } finally {
            @unlink($path);
        }

        $this->assertSame(SanctionStatus::Active, $entry->refresh()->status);
    }
}
