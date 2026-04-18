<?php

namespace App\Services;

use App\Models\Alert;

class NarrativeGenerator
{
    public function generateFromTriggers(array $triggers): string
    {
        $narrative = "Automated Suspicious Transaction Report Narrative\n\n";
        $narrative .= "Triggers Identified:\n";

        foreach ($triggers as $trigger) {
            $narrative .= '- '.($trigger['type'] ?? 'Unknown').': '.($trigger['description'] ?? 'No description')."\n";
        }

        $narrative .= "\n";

        return $narrative;
    }

    public function generateFromAlert(Alert $alert): string
    {
        $customer = $alert->customer;
        $flaggedTransaction = $alert->flaggedTransaction;

        $narrative = "SUSPICIOUS TRANSACTION REPORT NARRATIVE\n";
        $narrative .= "========================================\n\n";

        $narrative .= "1. CUSTOMER INFORMATION\n";
        $narrative .= "------------------------\n";
        if ($customer) {
            $narrative .= 'Name: '.($customer->full_name ?? 'N/A')."\n";
            $narrative .= 'ID Type: '.($customer->id_type ?? 'N/A')."\n";
            $narrative .= 'ID Number: '.($customer->id_number_decrypted ?? 'N/A')."\n";
            $narrative .= 'Nationality: '.($customer->nationality ?? 'N/A')."\n";
            $narrative .= 'Risk Rating: '.($customer->risk_rating ?? 'N/A')."\n";
            $narrative .= 'CDD Level: '.($customer->cdd_level?->value ?? 'N/A')."\n";
        } else {
            $narrative .= "Customer information not available.\n";
        }

        $narrative .= "\n2. ALERT DETAILS\n";
        $narrative .= "----------------\n";
        $alertType = 'N/A';
        if ($alert->type) {
            try {
                $alertType = $alert->type->label();
            } catch (\UnhandledMatchError $e) {
                $alertType = $alert->type->value ?? 'N/A';
            }
        }
        $narrative .= 'Alert Type: '.$alertType."\n";
        $narrative .= 'Priority: '.($alert->priority?->label() ?? $alert->priority ?? 'N/A')."\n";
        $narrative .= 'Alert Reason: '.($alert->reason ?? 'N/A')."\n";
        $narrative .= 'Risk Score: '.($alert->risk_score ?? 'N/A')."\n";
        $narrative .= 'Alert Source: '.($alert->source ?? 'N/A')."\n";

        $narrative .= "\n3. TRANSACTION DETAILS\n";
        $narrative .= "----------------------\n";
        if ($flaggedTransaction) {
            $narrative .= 'Transaction ID: '.$flaggedTransaction->id."\n";
            $narrative .= 'Transaction Date: '.($flaggedTransaction->created_at?->toDateString() ?? 'N/A')."\n";
            $narrative .= 'Amount (MYR): '.number_format((float) ($flaggedTransaction->amount_local ?? 0), 2)."\n";
            $narrative .= 'Currency: '.($flaggedTransaction->currency_code ?? 'N/A')."\n";
            $narrative .= 'Transaction Type: '.($flaggedTransaction->type?->value ?? $flaggedTransaction->type ?? 'N/A')."\n";
            $narrative .= 'Purpose: '.($flaggedTransaction->purpose ?? 'N/A')."\n";
        } else {
            $narrative .= "Transaction information not available.\n";
        }

        $narrative .= "\n4. SUSPICION INDICATORS\n";
        $narrative .= "----------------------\n";
        $narrative .= $this->generateSuspicionIndicators($alert);

        $narrative .= "\n5. CONCLUSION\n";
        $narrative .= "-----------\n";
        $narrative .= "Based on the above indicators, this transaction has been flagged as potentially suspicious and requires further investigation.\n";

        return $narrative;
    }

    protected function generateSuspicionIndicators(Alert $alert): string
    {
        $indicators = [];

        if ($alert->type) {
            try {
                $typeDescription = $alert->type->description();
            } catch (\UnhandledMatchError $e) {
                $typeDescription = $alert->type->value ?? 'Unknown';
            }
            $indicators[] = '- Alert Type: '.$typeDescription;
        }

        if ($alert->risk_score && $alert->risk_score >= 70) {
            $indicators[] = '- High risk score of '.$alert->risk_score.' indicates significant concern';
        }

        if ($alert->priority && $alert->priority->value === 'critical') {
            $indicators[] = '- Critical priority alert requires immediate attention';
        }

        if (empty($indicators)) {
            return "No specific indicators documented.\n";
        }

        return implode("\n", $indicators)."\n";
    }
}
