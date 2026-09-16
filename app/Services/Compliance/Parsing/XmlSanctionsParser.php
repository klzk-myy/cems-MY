<?php

namespace App\Services\Compliance\Parsing;

use App\Exceptions\Domain\SanctionsImportException;
use SimpleXMLElement;

/**
 * Parses the UN Consolidated List and OFAC SDN XML structures into
 * OpenSanctions-style item arrays.
 */
class XmlSanctionsParser implements SanctionsFeedParser
{
    /**
     * @return iterable<array-key, array<string, mixed>>
     */
    public function parse(string $filepath): iterable
    {
        $content = file_get_contents($filepath);
        if ($content === false) {
            throw new SanctionsImportException("Failed to read import file: {$filepath}", $filepath);
        }

        $previousLibxmlSetting = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        if ($xml === false) {
            $messages = array_map(fn ($e) => trim($e->message), libxml_get_errors());
            libxml_clear_errors();
            libxml_use_internal_errors($previousLibxmlSetting);
            throw new SanctionsImportException('Import file is not valid XML'.($messages !== [] ? ': '.implode('; ', $messages) : ''));
        }
        libxml_clear_errors();
        libxml_use_internal_errors($previousLibxmlSetting);

        foreach ($this->collectRecords($xml) as $record) {
            $parsed = $this->parseRecord($record);
            if ($parsed !== null) {
                yield $parsed;
            }
        }
    }

    /**
     * Collect record nodes from a sanctions XML document. Handles the UN
     * Consolidated List (INDIVIDUALS/INDIVIDUAL, ENTITIES/ENTITY) and the OFAC
     * SDN list (sdnList/sdnEntry) by recursing until a record-shaped element
     * is found.
     *
     * @return array<int, SimpleXMLElement>
     */
    protected function collectRecords(SimpleXMLElement $node): array
    {
        $records = [];

        foreach ($node->children() as $child) {
            $name = strtolower($child->getName());

            if (in_array($name, ['individual', 'entity', 'sdnentry', 'entry', 'item'], true)) {
                $records[] = $child;

                continue;
            }

            $records = array_merge($records, $this->collectRecords($child));
        }

        return $records;
    }

    /**
     * Normalise a sanctions XML record (UN or OFAC shape) into the
     * OpenSanctions-style array the entry mapper consumes.
     *
     * @return array<string, mixed>|null
     */
    protected function parseRecord(SimpleXMLElement $record): ?array
    {
        $value = fn (string $key) => isset($record->{$key}) ? trim((string) $record->{$key}) : null;
        $attr = fn (string $key) => isset($record[$key]) ? trim((string) $record[$key]) : null;

        $referenceNumber = $attr('dataid')
            ?? $attr('uid')
            ?? $value('REFERENCE_NUMBER')
            ?? $value('reference_number')
            ?? null;

        $name = $value('name')
            ?? $value('NAME')
            ?? $value('ENTITY')
            ?? $value('title')
            ?? $this->combineNames($record);

        if ($referenceNumber === null || $name === null) {
            return null;
        }

        return [
            'id' => $referenceNumber,
            'name' => $name,
            'birth_date' => $value('DATE_OF_BIRTH') ?? $value('birth_date') ?? $value('birthDate'),
            'nationality' => $value('NATIONALITY') ?? $value('nationality') ?? $value('NATIONALITY_VALUE'),
            'entity_type' => $value('UN_LIST_TYPE') ?? $value('sdnType') ?? $value('entity_type'),
            'aliases' => $this->collectAliases($record),
        ];
    }

    protected function combineNames(SimpleXMLElement $record): ?string
    {
        $first = isset($record->FIRST_NAME) ? trim((string) $record->FIRST_NAME) : null;
        if ($first === null && isset($record->firstName)) {
            $first = trim((string) $record->firstName);
        }

        $last = isset($record->LAST_NAME) ? trim((string) $record->LAST_NAME) : null;
        if ($last === null && isset($record->lastName)) {
            $last = trim((string) $record->lastName);
        }

        $middle = isset($record->SECOND_NAME) ? trim((string) $record->SECOND_NAME) : null;
        if ($middle === null && isset($record->middleName)) {
            $middle = trim((string) $record->middleName);
        }

        $third = isset($record->THIRD_NAME) ? trim((string) $record->THIRD_NAME) : null;

        if ($first === null && $last === null && $middle === null && $third === null) {
            return null;
        }

        $parts = array_values(array_filter([$last, $first, $middle, $third], fn ($p) => $p !== null && $p !== ''));

        return $parts !== [] ? implode(' ', $parts) : null;
    }

    /**
     * @return array<int, string>
     */
    protected function collectAliases(SimpleXMLElement $record): array
    {
        $aliases = [];

        // UN: <AKA><ALIAS_NAME>...</ALIAS_NAME></AKA>
        foreach ($record->AKA ?? [] as $aka) {
            $aliasName = isset($aka->ALIAS_NAME) ? trim((string) $aka->ALIAS_NAME) : null;
            if (! empty($aliasName)) {
                $aliases[] = $aliasName;
            }
        }

        // OFAC: <akaList><aka><firstName>..</firstName><lastName>..</lastName></aka></akaList>
        foreach ($record->akaList->aka ?? [] as $aka) {
            $first = isset($aka->firstName) ? trim((string) $aka->firstName) : '';
            $last = isset($aka->lastName) ? trim((string) $aka->lastName) : '';
            $aliasName = trim($first.' '.$last);
            if ($aliasName !== '') {
                $aliases[] = $aliasName;
            }
        }

        return $aliases;
    }
}
