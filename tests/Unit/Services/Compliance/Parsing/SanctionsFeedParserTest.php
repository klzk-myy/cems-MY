<?php

namespace Tests\Unit\Services\Compliance\Parsing;

use App\Services\Compliance\Parsing\CsvSanctionsParser;
use App\Services\Compliance\Parsing\OpenSanctionsJsonParser;
use App\Services\Compliance\Parsing\XmlSanctionsParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SanctionsFeedParserTest extends TestCase
{
    private function fixture(string $name): string
    {
        return base_path("tests/fixtures/sanctions/{$name}");
    }

    #[Test]
    public function json_parser_streams_nested_jsonl_entities(): void
    {
        $items = iterator_to_array((new OpenSanctionsJsonParser)->parse($this->fixture('targets.nested.json')), false);

        $this->assertCount(2, $items);
        $this->assertSame('os-001', $items[0]['id']);
        $this->assertSame(['John Doe', 'Johnny Doe'], $items[0]['name']);
        $this->assertSame('1980-01-15', $items[0]['birth_date']);
        $this->assertSame('MY', $items[0]['nationality']);
        $this->assertContains('Johnny Doe', $items[0]['aliases']);
        $this->assertContains('J. Doe', $items[0]['aliases']);
        $this->assertSame('Person', $items[0]['entity_type']);
    }

    #[Test]
    public function json_parser_reads_single_document_results(): void
    {
        $items = iterator_to_array((new OpenSanctionsJsonParser)->parse($this->fixture('single-doc.json')), false);

        $this->assertCount(1, $items);
        $this->assertSame('os-100', $items[0]['id']);
    }

    #[Test]
    public function xml_parser_reads_un_consolidated_list(): void
    {
        $items = iterator_to_array((new XmlSanctionsParser)->parse($this->fixture('un-list.xml')), false);

        $this->assertCount(2, $items);

        $individual = $items[0];
        $this->assertSame('6908645', $individual['id']);
        $this->assertSame('AZIZ AHMAD MOHAMMAD', $individual['name']);
        $this->assertSame('1965-07-10', $individual['birth_date']);
        $this->assertSame('MY', $individual['nationality']);
        $this->assertSame('Al-Qaida', $individual['entity_type']);
        $this->assertContains('AHMAD AZIZ', $individual['aliases']);

        $entity = $items[1];
        $this->assertSame('7701234', $entity['id']);
        $this->assertSame('SOME FOUNDATION', $entity['name']);
    }

    #[Test]
    public function csv_parser_maps_eu_consolidated_export(): void
    {
        $items = iterator_to_array((new CsvSanctionsParser)->parse($this->fixture('eu-list.csv')), false);

        $this->assertCount(2, $items);
        $this->assertSame('EU-1001', $items[0]['id']);
        $this->assertSame('DOE, Jane', $items[0]['name']);
        $this->assertSame('FR', $items[0]['nationality']);
        $this->assertSame('1980-05-14', $items[0]['birth_date']);
        $this->assertSame('P', $items[0]['entity_type']);
        $this->assertSame(['Jane Doe', 'J Doe'], $items[0]['aliases']);
    }

    /**
     * Headerless mode assigns numeric column indexes, which the named-key
     * row mapper cannot match — rows are skipped. This is pre-existing
     * behavior carried over verbatim from the monolithic service.
     */
    #[Test]
    public function csv_parser_headerless_rows_are_skipped_by_named_key_mapping(): void
    {
        $csv = tempnam(sys_get_temp_dir(), 'sanctions');
        file_put_contents($csv, "EU-2001,\"SMITH, John\",GB,1970-01-01,P\n");

        try {
            $items = iterator_to_array(
                (new CsvSanctionsParser)->parseWithHeader($csv, false),
                false
            );
        } finally {
            unlink($csv);
        }

        $this->assertCount(0, $items);
    }
}
