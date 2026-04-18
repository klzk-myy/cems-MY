<?php

namespace App\Services;

use App\Enums\StrStatus;
use App\Events\StrDraftGenerated;
use App\Jobs\SendNotificationJob;
use App\Models\FlaggedTransaction;
use App\Models\StrReport;
use App\Models\User;
use App\Notifications\Compliance\StrEscalationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * STR Report Service
 *
 * Manages Suspicious Transaction Report (STR) lifecycle including
 * generation, workflow, and BNM goAML submission.
 *
 * @see https://www.bnm.gov.my/goaml
 */
class StrReportService
{
    public function __construct(
        protected ComplianceService $complianceService,
        protected AuditService $auditService,
    ) {}

    /**
     * Generate a new STR number
     */
    public function generateStrNumber(): string
    {
        $year = date('Y');
        $month = date('m');
        $prefix = "STR-{$year}{$month}-";

        $lastStr = StrReport::where('str_no', 'like', $prefix.'%')
            ->orderBy('str_no', 'desc')
            ->first();

        if ($lastStr) {
            $lastNumber = (int) substr($lastStr->str_no, -5);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix.str_pad($newNumber, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Generate STR from a flagged transaction
     */
    public function generateFromAlert(FlaggedTransaction $alert): StrReport
    {
        return DB::transaction(function () use ($alert) {
            // Try transaction first
            if ($alert->getAttribute('transaction_id')) {
                $txnId = $alert->getAttribute('transaction_id');
                $transaction = \App\Models\Transaction::with('customer')->find($txnId);
                $customer = $transaction?->customer;
            } else {
                $customer = null;
                $transaction = null;
            }

            // Fall back to customer_id on alert
            if (! $customer && $alert->getAttribute('customer_id')) {
                $custId = $alert->getAttribute('customer_id');
                $customer = \App\Models\Customer::find($custId);
            }

            if (! $customer) {
                throw new \Exception('Cannot generate STR: Alert has no associated customer');
            }

            $branchId = $transaction?->branch_id ?? auth()->user()->branch_id ?? 1;

            // Use the alert creation date as the suspicion date
            $suspicionDate = $alert->created_at ?? now();

            $strReport = StrReport::create([
                'str_no' => $this->generateStrNumber(),
                'branch_id' => $branchId,
                'customer_id' => $customer->id,
                'alert_id' => $alert->id,
                'transaction_ids' => $transaction ? [$transaction->id] : [],
                'reason' => $this->buildReasonFromAlert($alert),
                'supporting_documents' => $this->gatherSupportingDocuments($alert),
                'status' => StrStatus::Draft,
                'created_by' => auth()->id(),
                'suspicion_date' => $suspicionDate,
            ]);

            // Calculate and store filing deadline
            $complianceService = $this->complianceService;
            $deadlineInfo = $complianceService->calculateStrDeadline($suspicionDate);
            $strReport->filing_deadline = $deadlineInfo['deadline'];
            $strReport->save();

            // Update the flag status to UnderReview
            $alert->update(['status' => \App\Enums\FlagStatus::UnderReview]);

            Log::info('STR Report generated from alert', [
                'str_id' => $strReport->id,
                'str_no' => $strReport->str_no,
                'alert_id' => $alert->id,
                'suspicion_date' => $suspicionDate->toDateTimeString(),
                'filing_deadline' => $deadlineInfo['deadline']->toDateTimeString(),
                'created_by' => auth()->id(),
            ]);

            // Audit log for STR generation
            $auditService = $this->auditService;
            $auditService->logStrAction('str_generated', $strReport->id, [
                'new' => [
                    'str_no' => $strReport->str_no,
                    'customer_id' => $customer->id,
                    'alert_id' => $alert->id,
                    'suspicion_date' => $suspicionDate->toDateTimeString(),
                    'filing_deadline' => $deadlineInfo['deadline']->toDateTimeString(),
                ],
            ]);

            Event::dispatch(new StrDraftGenerated($strReport));

            return $strReport;
        });
    }

    /**
     * Submit STR to BNM goAML system
     *
     * Implements certificate-based authentication with retry logic.
     *
     * @param  StrReport  $report  The STR report to submit
     * @return bool True if submission succeeded
     */
    public function submitToGoAML(StrReport $report): bool
    {
        if (! $report->status->canSubmit()) {
            Log::warning('STR cannot be submitted: invalid status', [
                'str_id' => $report->id,
                'status' => $report->status->value,
            ]);

            return false;
        }

        // Check if in test mode
        if ($this->isTestMode()) {
            Log::info('STR submission in TEST MODE', [
                'str_id' => $report->id,
                'str_no' => $report->str_no,
            ]);

            return $this->handleTestModeSubmission($report);
        }

        try {
            // Generate goAML XML payload
            $xmlPayload = $this->buildGoAMLPayload($report);

            // Log attempt
            Log::info('Submitting STR to goAML', [
                'str_id' => $report->id,
                'str_no' => $report->str_no,
                'payload_size' => strlen($xmlPayload),
            ]);

            // Call goAML API
            $result = $this->callGoAMLApi($report, $xmlPayload);

            if ($result['success']) {
                // Update report status
                $report->update([
                    'status' => StrStatus::Submitted,
                    'submitted_at' => now(),
                    'bnm_reference' => $result['reference'] ?? null,
                ]);

                // Clear retry tracking
                $report->update([
                    'last_error' => null,
                    'last_retry_at' => null,
                ]);

                Log::info('STR submitted successfully', [
                    'str_id' => $report->id,
                    'str_no' => $report->str_no,
                    'bnm_reference' => $result['reference'] ?? null,
                ]);

                // Audit log
                $auditService = $this->auditService;
                $auditService->logStrAction('str_submitted', $report->id, [
                    'str_no' => $report->str_no,
                    'bnm_reference' => $result['reference'] ?? null,
                    'submitted_at' => now()->toDateTimeString(),
                ]);

                return true;
            }

            // Handle submission failure
            return $this->handleSubmissionFailure($report, $result['error'] ?? 'Unknown error');

        } catch (\Exception $e) {
            Log::error('STR submission exception', [
                'str_id' => $report->id,
                'error' => $e->getMessage(),
            ]);

            return $this->handleSubmissionFailure($report, $e->getMessage());
        }
    }

    /**
     * Call goAML API with certificate authentication
     *
     * @param  StrReport  $report  The STR report
     * @param  string  $xmlPayload  The XML payload
     * @return array ['success' => bool, 'reference' => string|null, 'error' => string|null]
     */
    protected function callGoAMLApi(StrReport $report, string $xmlPayload): array
    {
        $endpoint = config('services.goaml.endpoint', 'https://goaml.bnm.gov.my/api/v1');
        $apiKey = config('services.goaml.api_key');

        // Validate certificate configuration
        $certIssues = $this->validateCertificateConfiguration();
        if (! empty($certIssues)) {
            Log::error('Certificate configuration missing', ['issues' => $certIssues]);

            return [
                'success' => false,
                'error' => 'Certificate configuration incomplete: '.implode(', ', $certIssues),
            ];
        }

        try {
            // Build HTTP request with certificate authentication
            $response = Http::withOptions([
                'cert' => [
                    config('services.goaml.cert_path'),
                    config('services.goaml.cert_password', ''),
                ],
                'ssl_key' => [
                    config('services.goaml.key_path'),
                    config('services.goaml.key_password', ''),
                ],
                'verify' => config('services.goaml.ca_path'),
                'timeout' => 60,
                'connect_timeout' => 30,
            ])
                ->withHeaders([
                    'Content-Type' => 'application/xml',
                    'Accept' => 'application/json',
                    'X-API-Key' => $apiKey,
                    'X-STR-Reference' => $report->str_no,
                ])
                ->withBody($xmlPayload, 'application/xml')
                ->post($endpoint.'/str/submit');

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'reference' => $data['reference_number'] ?? $data['bnm_reference'] ?? null,
                    'error' => null,
                ];
            }

            // Log HTTP error
            Log::warning('goAML API returned error', [
                'http_code' => $response->status(),
                'response' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => "HTTP {$response->status()}: ".$response->body(),
            ];

        } catch (\Exception $e) {
            Log::error('goAML API call failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Handle submission failure with retry logic
     */
    protected function handleSubmissionFailure(StrReport $report, string $error): bool
    {
        $maxRetries = config('services.goaml.max_retries', 5);
        $newRetryCount = ($report->retry_count ?? 0) + 1;

        $report->update([
            'status' => StrStatus::Failed,
            'retry_count' => $newRetryCount,
            'last_error' => $error,
            'last_retry_at' => now(),
        ]);

        Log::error('STR submission failed', [
            'str_id' => $report->id,
            'str_no' => $report->str_no,
            'retry_count' => $newRetryCount,
            'max_retries' => $maxRetries,
            'error' => $error,
        ]);

        // Audit log
        $auditService = $this->auditService;
        $auditService->logStrAction('str_submission_failed', $report->id, [
            'str_no' => $report->str_no,
            'retry_count' => $newRetryCount,
            'error' => $error,
        ]);

        // Dispatch retry job with exponential backoff
        if ($newRetryCount < $maxRetries) {
            $delay = $this->calculateRetryDelay($newRetryCount);
            \App\Jobs\SubmitStrToGoAmlJob::dispatch($report)
                ->delay(now()->addSeconds($delay));

            Log::info('STR retry scheduled', [
                'str_id' => $report->id,
                'str_no' => $report->str_no,
                'delay_minutes' => $delay / 60,
                'attempt' => $newRetryCount,
            ]);
        } else {
            // Max retries exceeded - escalate
            $this->escalateToSupervisor($report);
        }

        return false;
    }

    /**
     * Calculate retry delay with exponential backoff
     *
     * Delays: 1min, 5min, 10min, 30min, 60min
     */
    protected function calculateRetryDelay(int $attempt): int
    {
        $delays = [60, 300, 600, 1800, 3600]; // seconds

        return $delays[min($attempt - 1, count($delays) - 1)] ?? 3600;
    }

    /**
     * Handle test mode submission
     */
    protected function handleTestModeSubmission(StrReport $report): bool
    {
        $testResponse = $this->getTestModeResponse();

        if ($testResponse) {
            // Simulate success
            $report->update([
                'status' => StrStatus::Submitted,
                'submitted_at' => now(),
                'bnm_reference' => 'TEST-'.strtoupper(uniqid()),
            ]);

            Log::info('STR submitted successfully (TEST MODE)', [
                'str_id' => $report->id,
                'str_no' => $report->str_no,
            ]);
        } else {
            // Simulate failure
            $this->handleSubmissionFailure($report, 'Simulated test failure');
        }

        return $testResponse;
    }

    /**
     * Check if test mode is enabled
     */
    protected function isTestMode(): bool
    {
        return config('services.goaml.test_mode', false) === true;
    }

    /**
     * Get test mode response (success or failure)
     */
    protected function getTestModeResponse(): bool
    {
        return config('services.goaml.test_mode_response', true);
    }

    /**
     * Retry a failed STR submission
     */
    public function retrySubmission(StrReport $report): bool
    {
        if (! $report->status->canRetry()) {
            Log::warning('STR cannot be retried: invalid status', [
                'str_id' => $report->id,
                'status' => $report->status->value,
            ]);

            return false;
        }

        $maxRetries = config('services.goaml.max_retries', 5);

        if ($report->retry_count >= $maxRetries) {
            Log::warning('STR retry limit exceeded - escalation required', [
                'str_id' => $report->id,
                'str_no' => $report->str_no,
                'retry_count' => $report->retry_count,
                'max_retries' => $maxRetries,
            ]);

            $this->escalateToSupervisor($report);

            return false;
        }

        Log::info('Retrying STR submission', [
            'str_id' => $report->id,
            'str_no' => $report->str_no,
            'attempt' => ($report->retry_count ?? 0) + 1,
        ]);

        // Revert status to allow submission
        $report->update(['status' => StrStatus::PendingApproval]);
        $report->refresh();

        // Attempt submission
        return $this->submitToGoAML($report);
    }

    /**
     * Escalate repeatedly failed STR to supervisor
     */
    protected function escalateToSupervisor(StrReport $report): void
    {
        Log::critical('STR submission escalated to supervisor', [
            'str_id' => $report->id,
            'str_no' => $report->str_no,
            'retry_count' => $report->retry_count,
            'filing_deadline' => $report->filing_deadline?->toDateTimeString(),
            'is_overdue' => $report->isOverdue(),
        ]);

        // Update status to reflect escalation
        $report->update([
            'last_error' => 'Escalated to supervisor after '.$report->retry_count.' failed attempts',
        ]);

        // Audit log
        $auditService = $this->auditService;
        $auditService->logStrAction('str_escalated', $report->id, [
            'str_no' => $report->str_no,
            'retry_count' => $report->retry_count,
            'filing_deadline' => $report->filing_deadline?->toDateTimeString(),
            'is_overdue' => $report->isOverdue(),
        ]);

        // Send notification
        $this->sendEscalationNotification($report);
    }

    /**
     * Send escalation notification via queue
     */
    protected function sendEscalationNotification(StrReport $report): void
    {
        try {
            $supervisors = User::whereIn('role', ['compliance_officer', 'manager', 'admin'])
                ->where('is_active', true)
                ->get();

            if ($supervisors->isEmpty()) {
                Log::warning('No supervisors found for STR escalation', [
                    'str_id' => $report->id,
                    'str_no' => $report->str_no,
                ]);

                return;
            }

            $notification = new StrEscalationNotification($report);

            // Dispatch notification via queue for each supervisor
            foreach ($supervisors as $supervisor) {
                SendNotificationJob::dispatch($supervisor, $notification, ['mail', 'database'])
                    ->onQueue('notifications');
            }

            Log::info('STR escalation notifications dispatched', [
                'str_id' => $report->id,
                'str_no' => $report->str_no,
                'supervisors_notified' => $supervisors->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to dispatch STR escalation notifications', [
                'str_id' => $report->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Track BNM acknowledgment
     */
    public function trackSubmission(StrReport $report, string $bnmRef): void
    {
        $report->update([
            'bnm_reference' => $bnmRef,
            'status' => StrStatus::Acknowledged,
        ]);

        Log::info('STR submission tracked with BNM reference', [
            'str_id' => $report->id,
            'str_no' => $report->str_no,
            'bnm_reference' => $bnmRef,
        ]);
    }

    /**
     * Submit STR for review
     */
    public function submitForReview(StrReport $report): bool
    {
        if (! $report->isDraft()) {
            return false;
        }

        $report->update(['status' => StrStatus::PendingReview]);

        return true;
    }

    /**
     * Submit STR for approval
     */
    public function submitForApproval(StrReport $report): bool
    {
        if (! $report->status->canApprove()) {
            return false;
        }

        $report->update([
            'status' => StrStatus::PendingApproval,
            'reviewed_by' => auth()->id(),
        ]);

        return true;
    }

    /**
     * Approve STR
     */
    public function approve(StrReport $report): bool
    {
        if (! $report->status->canApprove()) {
            return false;
        }

        $report->update([
            'status' => StrStatus::Submitted,
            'approved_by' => auth()->id(),
        ]);

        return true;
    }

    /**
     * Build reason text from alert
     */
    protected function buildReasonFromAlert(FlaggedTransaction $alert): string
    {
        $reason = "Suspicious Transaction Alert - {$alert->flag_type->value}\n\n";
        $reason .= "Flag Reason: {$alert->flag_reason}\n";

        if ($alert->transaction) {
            $txn = $alert->transaction;
            $reason .= "\nTransaction Details:\n";
            $reason .= '- Transaction ID: #'.($txn->id ?? 'N/A')."\n";
            $reason .= '- Amount: RM '.($txn->amount_local ?? 'N/A')."\n";
            $reason .= '- Currency: '.($txn->currency_code ?? 'N/A')."\n";
            $reason .= '- Date: '.($txn->created_at?->format('Y-m-d H:i:s') ?? 'N/A')."\n";
        }

        return $reason;
    }

    /**
     * Gather supporting documents
     */
    protected function gatherSupportingDocuments(FlaggedTransaction $alert): array
    {
        $documents = [];

        $customer = $alert->transaction?->customer;
        if ($customer) {
            $customerDocs = $customer->documents()->get();
            foreach ($customerDocs as $doc) {
                $documents[] = [
                    'type' => 'customer_document',
                    'id' => $doc->id,
                    'name' => $doc->document_type,
                ];
            }
        }

        return $documents;
    }

    /**
     * Build goAML XML payload
     */
    protected function buildGoAMLPayload(StrReport $report): string
    {
        $generator = new GoAmlXmlGenerator;

        return $generator->generate($report);
    }

    /**
     * Validate certificate configuration
     */
    public function validateCertificateConfiguration(): array
    {
        $config = config('services.goaml', []);
        $missing = [];

        // Required for production
        $required = ['cert_path', 'key_path', 'ca_path'];

        foreach ($required as $key) {
            if (empty($config[$key])) {
                $missing[] = $key;

                continue;
            }

            $path = $config[$key];

            if (! file_exists($path)) {
                $missing[] = "{$key} (file not found: {$path})";

                continue;
            }

            if (! is_readable($path)) {
                $missing[] = "{$key} (file not readable: {$path})";

                continue;
            }

            // Validate certificate/key content
            if ($key === 'cert_path') {
                $content = file_get_contents($path);
                if (strpos($content, '-----BEGIN CERTIFICATE-----') === false) {
                    $missing[] = "{$key} (invalid certificate format: {$path})";
                }
            } elseif ($key === 'key_path') {
                $content = file_get_contents($path);
                if (strpos($content, '-----BEGIN') === false) {
                    $missing[] = "{$key} (invalid key format: {$path})";
                }
            } elseif ($key === 'ca_path') {
                $content = file_get_contents($path);
                if (strpos($content, '-----BEGIN CERTIFICATE-----') === false) {
                    $missing[] = "{$key} (invalid CA certificate format: {$path})";
                }
            }
        }

        // Validate certificate and key match if both are present
        if (! in_array('cert_path', $missing) && ! in_array('key_path', $missing)) {
            try {
                $certPath = $config['cert_path'];
                $keyPath = $config['key_path'];

                // Extract public key from certificate
                $certContent = file_get_contents($certPath);
                $cert = openssl_x509_read($certContent);
                if (! $cert) {
                    $missing[] = 'cert_path (unable to read certificate)';
                } else {
                    $certPubKey = openssl_pkey_get_public($cert);
                    $keyContent = file_get_contents($keyPath);
                    $key = openssl_pkey_get_private($keyContent, $config['key_password'] ?? '');

                    if (! $key) {
                        $missing[] = 'key_path (unable to read private key)';
                    } else {
                        // Check if key matches certificate
                        $keyDetails = openssl_pkey_get_details($key);
                        $certDetails = openssl_pkey_get_details($certPubKey);

                        if ($keyDetails['key'] !== $certDetails['key']) {
                            $missing[] = 'key_path (private key does not match certificate)';
                        }
                    }
                }
            } catch (\Exception $e) {
                $missing[] = 'certificate validation failed: '.$e->getMessage();
            }
        }

        return $missing;
    }
}
