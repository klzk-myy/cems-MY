<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * GoAML Mock Server
 *
 * Simulates BNM goAML API responses for testing purposes.
 * Validates XML structure and returns appropriate responses.
 */
class GoAmlMockServer
{
    /**
     * Mock goAML API endpoint
     */
    protected string $endpoint = 'https://goaml-test.bnm.gov.my/api/v1';

    /**
     * Validation results
     */
    protected array $validationResults = [];

    /**
     * Process a mock STR submission
     *
     * @param  string  $xmlPayload  The XML payload
     * @return array ['success' => bool, 'reference' => string|null, 'errors' => array]
     */
    public function submit(string $xmlPayload): array
    {
        Log::info('GoAML Mock Server: Processing submission', [
            'payload_size' => strlen($xmlPayload),
        ]);

        // Validate XML structure
        $validation = $this->validateXmlStructure($xmlPayload);

        if (! $validation['valid']) {
            Log::warning('GoAML Mock Server: XML validation failed', [
                'errors' => $validation['errors'],
            ]);

            return [
                'success' => false,
                'reference' => null,
                'errors' => $validation['errors'],
            ];
        }

        // Simulate random failures if configured
        if ($this->shouldSimulateFailure()) {
            return [
                'success' => false,
                'reference' => null,
                'errors' => ['Simulated server error for testing'],
            ];
        }

        // Generate mock reference number
        $reference = 'BNM-STR-'.strtoupper(uniqid()).'-'.now()->format('Ymd');

        Log::info('GoAML Mock Server: Submission successful', [
            'reference' => $reference,
        ]);

        return [
            'success' => true,
            'reference' => $reference,
            'errors' => [],
        ];
    }

    /**
     * Validate XML structure against goAML schema
     *
     * @param  string  $xml  The XML to validate
     * @return array ['valid' => bool, 'errors' => array]
     */
    protected function validateXmlStructure(string $xml): array
    {
        $errors = [];
        $valid = true;

        // Check if XML is well-formed
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument;

        if (! @$dom->loadXML($xml)) {
            $libxmlErrors = libxml_get_errors();
            foreach ($libxmlErrors as $error) {
                $errors[] = "XML Parse Error (Line {$error->line}): {$error->message}";
            }
            libxml_clear_errors();

            return ['valid' => false, 'errors' => $errors];
        }

        libxml_clear_errors();

        // Check for required elements
        $requiredElements = [
            'report',
            'report_metadata',
            'reporting_entity',
            'suspicious_activity',
            'transactions',
        ];

        foreach ($requiredElements as $element) {
            $nodes = $dom->getElementsByTagName($element);
            if ($nodes->length === 0) {
                $errors[] = "Missing required element: {$element}";
                $valid = false;
            }
        }

        // Check for report metadata
        $metadata = $dom->getElementsByTagName('report_metadata')->item(0);
        if ($metadata) {
            $requiredMetadata = ['report_type', 'report_reference', 'submission_date'];
            foreach ($requiredMetadata as $meta) {
                if ($metadata->getElementsByTagName($meta)->length === 0) {
                    $errors[] = "Missing required metadata: {$meta}";
                    $valid = false;
                }
            }
        }

        // Check for transaction data
        $transactions = $dom->getElementsByTagName('transactions')->item(0);
        if (! $transactions || $transactions->getElementsByTagName('transaction')->length === 0) {
            $errors[] = 'No transaction elements found';
            $valid = false;
        }

        $this->validationResults = [
            'valid' => $valid,
            'errors' => $errors,
            'elements_found' => $this->countElements($dom),
        ];

        return $this->validationResults;
    }

    /**
     * Count XML elements for debugging
     */
    protected function countElements(\DOMDocument $dom): array
    {
        $counts = [];
        $elementNames = ['report', 'report_metadata', 'reporting_entity', 'suspicious_activity', 'transactions', 'transaction', 'customer'];

        foreach ($elementNames as $name) {
            $counts[$name] = $dom->getElementsByTagName($name)->length;
        }

        return $counts;
    }

    /**
     * Determine if we should simulate a failure
     */
    protected function shouldSimulateFailure(): bool
    {
        if (! config('str.mock_server.simulate_failures', false)) {
            return false;
        }

        // 10% chance of failure
        return random_int(1, 10) === 1;
    }

    /**
     * Get validation results from last submission
     */
    public function getValidationResults(): array
    {
        return $this->validationResults;
    }

    /**
     * Generate a test goAML XML for validation testing
     */
    public function generateTestXml(): string
    {
        $strNo = 'STR-TEST-'.now()->format('Ymd').'-001';
        $submissionDate = now()->format('Y-m-d\TH:i:s');

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<report xmlns="urn:goAML:report:1.0" version="1.0">
    <report_metadata>
        <report_type>STR</report_type>
        <report_reference>{$strNo}</report_reference>
        <submission_date>{$submissionDate}</submission_date>
        <priority>NORMAL</priority>
        <confidentiality>CONFIDENTIAL</confidentiality>
    </report_metadata>
    <reporting_entity>
        <entity_name>Test MSB</entity_name>
        <license_number>MSB-TEST-001</license_number>
        <branch_code>HQ</branch_code>
        <branch_name>Head Office</branch_name>
        <contact>
            <contact_name>Test Officer</contact_name>
            <contact_phone>+603-12345678</contact_phone>
        </contact>
        <reporting_officer>
            <name>Test User</name>
            <role>Compliance Officer</role>
        </reporting_officer>
    </reporting_entity>
    <suspicious_activity>
        <activity_type>SUSPICIOUS_TRANSACTION</activity_type>
        <suspicion_reason><![CDATA[Test suspicious activity]]></suspicion_reason>
        <indicators>
            <indicator>STRUCTURING_BEHAVIOR</indicator>
        </indicators>
    </suspicious_activity>
    <transactions>
        <transaction>
            <transaction_id>1</transaction_id>
            <transaction_date>{$submissionDate}</transaction_date>
            <transaction_type>Buy</transaction_type>
            <amounts>
                <local_amount currency="MYR" value="50000.00"/>
                <foreign_amount currency="USD" value="10600.00"/>
                <exchange_rate>4.7200</exchange_rate>
            </amounts>
            <currency>USD</currency>
            <purpose><![CDATA[Investment]]></purpose>
            <source_of_funds>Business Revenue</source_of_funds>
            <status>Completed</status>
        </transaction>
    </transactions>
    <customer>
        <customer_id>1</customer_id>
        <name>Test Customer</name>
        <id_type>MyKad</id_type>
        <id_number_masked>********890</id_number_masked>
        <nationality>Malaysian</nationality>
        <risk_rating>Medium</risk_rating>
        <pep_status>NO</pep_status>
        <sanctions_match>NO</sanctions_match>
    </customer>
</report>
XML;
    }

    /**
     * Log a submission for debugging
     */
    public function logSubmission(array $result): void
    {
        Log::info('GoAML Mock Server: Submission logged', [
            'result' => $result,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }
}
