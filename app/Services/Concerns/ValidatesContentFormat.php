<?php

namespace App\Services\Concerns;

trait ValidatesContentFormat
{
    /**
     * Validate that the given content is well-formed XML.
     */
    protected function validateXml(string $content): bool
    {
        libxml_use_internal_errors(true);
        $result = simplexml_load_string($content);

        return $result !== false;
    }

    /**
     * Validate that the given content is well-formed JSON. Accepts a single
     * JSON document or JSONL (one JSON object per line — the OpenSanctions
     * targets.nested.json exports are JSONL).
     */
    protected function validateJson(string $content): bool
    {
        json_decode($content);
        if (json_last_error() === JSON_ERROR_NONE) {
            return true;
        }

        $lines = preg_split('/\r?\n/', $content);
        if ($lines === false) {
            return false;
        }

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            json_decode($line);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate that the given content is a CSV-like table.
     */
    protected function validateCsv(string $content): bool
    {
        $lines = explode("\n", $content);
        if (count($lines) < 2) {
            return false;
        }
        $firstLine = $lines[0];

        return str_contains($firstLine, ',') || str_contains($firstLine, "\t");
    }
}
