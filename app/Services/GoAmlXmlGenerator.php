<?php

namespace App\Services;

use App\Models\StrReport;
use DOMDocument;
use DOMElement;

/**
 * GoAML XML Generator
 *
 * Generates BNM-compliant goAML XML format for Suspicious Transaction Reports.
 * goAML is the goAML standard used by Bank Negara Malaysia for STR submissions.
 *
 * @see https://www.bnm.gov.my/goaml
 */
class GoAmlXmlGenerator
{
    /**
     * XML namespace for goAML
     */
    protected const XML_NS = 'urn:goAML:report:1.0';

    /**
     * XML Schema version
     */
    protected const SCHEMA_VERSION = '1.0';

    /**
     * Reporting entity details
     */
    protected array $reportingEntity;

    /**
     * Generate goAML XML from STR report
     *
     * @param  StrReport  $report  The STR report to convert
     * @return string The generated XML
     */
    public function generate(StrReport $report): string
    {
        $this->loadReportingEntity();

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Create root element
        $reportElement = $dom->createElementNS(self::XML_NS, 'report');
        $reportElement->setAttributeNS(
            'http://www.w3.org/2000/xmlns/',
            'xmlns:xsi',
            'http://www.w3.org/2001/XMLSchema-instance'
        );
        $reportElement->setAttribute('version', self::SCHEMA_VERSION);
        $dom->appendChild($reportElement);

        // Add report metadata
        $this->addReportMetadata($dom, $reportElement, $report);

        // Add reporting entity
        $this->addReportingEntity($dom, $reportElement, $report);

        // Add suspicious activity
        $this->addSuspiciousActivity($dom, $reportElement, $report);

        // Add transactions
        $this->addTransactions($dom, $reportElement, $report);

        // Add customer information (masked)
        $this->addCustomerInformation($dom, $reportElement, $report);

        return $dom->saveXML();
    }

    /**
     * Load reporting entity configuration
     */
    protected function loadReportingEntity(): void
    {
        $this->reportingEntity = [
            'name' => config('cems.goaml.reporter_name', 'CEMS-MY MSB'),
            'branch_code' => config('cems.goaml.branch_code', 'HQ'),
            'license_number' => config('cems.license_number', 'MSB-XXXXXXX'),
            'address' => config('cems.goaml.reporting_address', ''),
            'contact_name' => config('cems.bnm_reporting.contact_name', ''),
            'contact_phone' => config('cems.bnm_reporting.contact_phone', ''),
            'contact_email' => config('cems.bnm_reporting.contact_email', ''),
        ];
    }

    /**
     * Add report metadata section
     */
    protected function addReportMetadata(DOMDocument $dom, DOMElement $parent, StrReport $report): void
    {
        $metadata = $dom->createElement('report_metadata');

        // Report type
        $metadata->appendChild($dom->createElement('report_type', 'STR'));

        // STR reference number
        $metadata->appendChild($dom->createElement('report_reference', $report->str_no));

        // Submission date/time
        $submissionDate = $report->submitted_at ?? now();
        $metadata->appendChild(
            $dom->createElement('submission_date', $submissionDate->format('Y-m-d\TH:i:s'))
        );

        // Suspicion date (when suspicion first arose)
        if ($report->suspicion_date) {
            $metadata->appendChild(
                $dom->createElement('suspicion_date', $report->suspicion_date->format('Y-m-d'))
            );
        }

        // Report priority
        $priority = $report->isOverdue() ? 'HIGH' : 'NORMAL';
        $metadata->appendChild($dom->createElement('priority', $priority));

        // Confidentiality
        $metadata->appendChild($dom->createElement('confidentiality', 'CONFIDENTIAL'));

        $parent->appendChild($metadata);
    }

    /**
     * Add reporting entity section
     */
    protected function addReportingEntity(DOMDocument $dom, DOMElement $parent, StrReport $report): void
    {
        $entity = $dom->createElement('reporting_entity');

        // Entity name
        $entity->appendChild(
            $dom->createElement('entity_name', $this->reportingEntity['name'])
        );

        // MSB license number
        $entity->appendChild(
            $dom->createElement('license_number', $this->reportingEntity['license_number'])
        );

        // Branch code
        $entity->appendChild(
            $dom->createElement('branch_code', $this->reportingEntity['branch_code'])
        );

        // Branch name
        $branchName = $report->branch?->name ?? 'Head Office';
        $entity->appendChild($dom->createElement('branch_name', $branchName));

        // Contact information
        $contact = $dom->createElement('contact');
        if ($this->reportingEntity['contact_name']) {
            $contact->appendChild(
                $dom->createElement('contact_name', $this->reportingEntity['contact_name'])
            );
        }
        if ($this->reportingEntity['contact_phone']) {
            $contact->appendChild(
                $dom->createElement('contact_phone', $this->reportingEntity['contact_phone'])
            );
        }
        if ($this->reportingEntity['contact_email']) {
            $contact->appendChild(
                $dom->createElement('contact_email', $this->reportingEntity['contact_email'])
            );
        }
        $entity->appendChild($contact);

        // Reporting officer
        $officer = $dom->createElement('reporting_officer');
        $officer->appendChild(
            $dom->createElement('name', $report->creator?->full_name ?? 'Unknown')
        );
        $officer->appendChild(
            $dom->createElement('role', $report->creator?->role->value ?? 'Unknown')
        );
        $entity->appendChild($officer);

        $parent->appendChild($entity);
    }

    /**
     * Add suspicious activity section
     */
    protected function addSuspiciousActivity(DOMDocument $dom, DOMElement $parent, StrReport $report): void
    {
        $activity = $dom->createElement('suspicious_activity');

        // Activity type codes (goAML codes)
        $activity->appendChild($dom->createElement('activity_type', 'SUSPICIOUS_TRANSACTION'));

        // Reason for suspicion
        $reason = $dom->createElement('suspicion_reason');
        $this->appendCdata($dom, $reason, $report->reason ?? 'No reason provided');
        $activity->appendChild($reason);

        // Activity indicator
        $indicators = $this->identifyActivityIndicators($report);
        $indicatorsElement = $dom->createElement('indicators');
        foreach ($indicators as $indicator) {
            $indicatorsElement->appendChild($dom->createElement('indicator', $indicator));
        }
        $activity->appendChild($indicatorsElement);

        // Risk assessment
        if ($report->customer?->risk_rating) {
            $risk = $dom->createElement('risk_assessment');
            $risk->appendChild($dom->createElement('customer_risk', $report->customer->risk_rating));
            $activity->appendChild($risk);
        }

        // Alert reference
        if ($report->alert_id) {
            $activity->appendChild(
                $dom->createElement('alert_reference', (string) $report->alert_id)
            );
        }

        $parent->appendChild($activity);
    }

    /**
     * Identify activity indicators based on alert
     */
    protected function identifyActivityIndicators(StrReport $report): array
    {
        $indicators = [];

        // Load alert if available
        if ($report->alert) {
            $flagType = $report->alert->flag_type?->value ?? '';

            $indicators[] = match ($flagType) {
                'Structuring' => 'STRUCTURING_BEHAVIOR',
                'Velocity' => 'VELOCITY_ANOMALY',
                'LargeAmount' => 'LARGE_TRANSACTION',
                'SanctionMatch' => 'SANCTIONS_MATCH',
                'PepStatus' => 'POLITICALLY_EXPOSED_PERSON',
                'HighRiskCountry' => 'HIGH_RISK_JURISDICTION',
                default => 'UNUSUAL_ACTIVITY',
            };
        }

        // Check for PEP
        if ($report->customer?->pep_status) {
            $indicators[] = 'PEP_INVOLVED';
        }

        // Check for sanctions
        if ($report->customer?->sanction_hit) {
            $indicators[] = 'SANCTIONS_LIST_MATCH';
        }

        if (empty($indicators)) {
            $indicators[] = 'SUSPICIOUS_BEHAVIOR';
        }

        return $indicators;
    }

    /**
     * Add transactions section
     */
    protected function addTransactions(DOMDocument $dom, DOMElement $parent, StrReport $report): void
    {
        $transactions = $dom->createElement('transactions');

        $transactionList = $report->transactions();

        if ($transactionList->isEmpty()) {
            // Add empty transactions node with count attribute
            $transactions->setAttribute('count', '0');
            $transactions->appendChild($dom->createElement('note', 'No transactions included in this report'));
        } else {
            foreach ($transactionList as $transaction) {
                $txn = $dom->createElement('transaction');

                // Transaction ID
                $txn->appendChild(
                    $dom->createElement('transaction_id', (string) $transaction->id)
                );

                // Transaction date
                if ($transaction->created_at) {
                    $txn->appendChild(
                        $dom->createElement(
                            'transaction_date',
                            $transaction->created_at->format('Y-m-d\TH:i:s')
                        )
                    );
                }

                // Transaction type
                $type = $transaction->type?->value ?? 'Unknown';
                $txn->appendChild($dom->createElement('transaction_type', $type));

                // Amounts
                $amounts = $dom->createElement('amounts');

                // Local currency (MYR)
                $local = $dom->createElement('local_amount');
                $local->setAttribute('currency', 'MYR');
                $local->setAttribute('value', $transaction->amount_local ?? '0.00');
                $amounts->appendChild($local);

                // Foreign currency
                if ($transaction->amount_foreign && $transaction->currency_code) {
                    $foreign = $dom->createElement('foreign_amount');
                    $foreign->setAttribute('currency', $transaction->currency_code);
                    $foreign->setAttribute('value', $transaction->amount_foreign);
                    $amounts->appendChild($foreign);

                    // Exchange rate
                    if ($transaction->rate) {
                        $amounts->appendChild(
                            $dom->createElement('exchange_rate', $transaction->rate)
                        );
                    }
                }

                $txn->appendChild($amounts);

                // Currency code
                $txn->appendChild(
                    $dom->createElement('currency', $transaction->currency_code ?? 'MYR')
                );

                // Purpose
                if ($transaction->purpose) {
                    $purpose = $dom->createElement('purpose');
                    $this->appendCdata($dom, $purpose, $transaction->purpose);
                    $txn->appendChild($purpose);
                }

                // Source of funds
                if ($transaction->source_of_funds) {
                    $txn->appendChild(
                        $dom->createElement('source_of_funds', $transaction->source_of_funds)
                    );
                }

                // Status
                $txn->appendChild(
                    $dom->createElement('status', $transaction->status?->value ?? 'Unknown')
                );

                $transactions->appendChild($txn);
            }
        }

        $parent->appendChild($transactions);
    }

    /**
     * Add masked customer information
     */
    protected function addCustomerInformation(DOMDocument $dom, DOMElement $parent, StrReport $report): void
    {
        if (! $report->customer) {
            return;
        }

        $customer = $dom->createElement('customer');
        $customer->setAttribute('type', 'INDIVIDUAL');

        // Customer ID
        $customer->appendChild(
            $dom->createElement('customer_id', (string) $report->customer->id)
        );

        // Name
        $customer->appendChild(
            $dom->createElement('name', $report->customer->full_name)
        );

        // ID Type
        if ($report->customer->id_type) {
            $customer->appendChild(
                $dom->createElement('id_type', $report->customer->id_type)
            );
        }

        // Masked ID Number
        $idNumber = $this->maskIdNumber($report->customer->id_number_encrypted);
        $customer->appendChild(
            $dom->createElement('id_number_masked', $idNumber)
        );

        // Nationality
        if ($report->customer->nationality) {
            $customer->appendChild(
                $dom->createElement('nationality', $report->customer->nationality)
            );
        }

        // Date of birth
        if ($report->customer->date_of_birth) {
            $customer->appendChild(
                $dom->createElement(
                    'date_of_birth',
                    $report->customer->date_of_birth->format('Y-m-d')
                )
            );
        }

        // Risk rating
        if ($report->customer->risk_rating) {
            $customer->appendChild(
                $dom->createElement('risk_rating', $report->customer->risk_rating)
            );
        }

        // CDD level
        if ($report->customer->cdd_level) {
            $customer->appendChild(
                $dom->createElement('cdd_level', $report->customer->cdd_level)
            );
        }

        // PEP status
        $customer->appendChild(
            $dom->createElement(
                'pep_status',
                $report->customer->pep_status ? 'YES' : 'NO'
            )
        );

        // Sanctions match
        $customer->appendChild(
            $dom->createElement(
                'sanctions_match',
                $report->customer->sanction_hit ? 'YES' : 'NO'
            )
        );

        // Occupation
        if ($report->customer->occupation) {
            $customer->appendChild(
                $dom->createElement('occupation', $report->customer->occupation)
            );
        }

        // Employer
        if ($report->customer->employer_name) {
            $customer->appendChild(
                $dom->createElement('employer', $report->customer->employer_name)
            );
        }

        // Masked address
        if ($report->customer->address) {
            $maskedAddress = $this->maskAddress($report->customer->address);
            $customer->appendChild(
                $dom->createElement('address_masked', $maskedAddress)
            );
        }

        $parent->appendChild($customer);
    }

    /**
     * Mask ID number for privacy
     */
    protected function maskIdNumber(?string $encryptedId): string
    {
        if (! $encryptedId) {
            return '***-***-***';
        }

        try {
            $id = decrypt($encryptedId);
            if (strlen($id) < 4) {
                return '***';
            }

            // Mask all but last 4 characters
            $length = strlen($id);
            $visible = substr($id, -4);
            $masked = str_repeat('*', $length - 4);

            return $masked.$visible;
        } catch (\Exception $e) {
            return '***-***-***';
        }
    }

    /**
     * Mask address for privacy
     */
    protected function maskAddress(?string $address): string
    {
        if (! $address) {
            return '[Address withheld]';
        }

        // Show only state/city portion, mask street address
        $parts = explode(',', $address);
        if (count($parts) > 1) {
            // Keep last 2 parts (city, state)
            $visible = implode(', ', array_slice($parts, -2));

            return '[Street address withheld], '.$visible;
        }

        return '[Address withheld]';
    }

    /**
     * Append CDATA section
     */
    protected function appendCdata(DOMDocument $dom, DOMElement $element, string $content): void
    {
        $cdata = $dom->createCDATASection($content);
        $element->appendChild($cdata);
    }

    /**
     * Validate generated XML against goAML schema
     *
     * @param  string  $xml  The XML to validate
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validate(string $xml): array
    {
        libxml_use_internal_errors(true);

        $dom = new DOMDocument;
        $dom->loadXML($xml);

        $errors = [];
        foreach (libxml_get_errors() as $error) {
            $errors[] = [
                'level' => $error->level,
                'message' => trim($error->message),
                'line' => $error->line,
            ];
        }
        libxml_clear_errors();

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Generate XML filename for submission
     */
    public function generateFilename(StrReport $report): string
    {
        $timestamp = now()->format('Ymd_His');
        $strNo = str_replace(['-', ' '], '_', $report->str_no);

        return "STR_{$strNo}_{$timestamp}.xml";
    }
}
