<?php

namespace App\Services\Customer;

use App\Enums\CddLevel;
use App\Enums\RiskRating;
use App\Enums\StrReportStatus;
use App\Models\Alert;
use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\StrReport;
use App\Models\User;
use App\Repositories\CustomerRepository;
use App\Services\Audit\AuditTrailHelper;
use App\Services\AuditService;
use App\Services\Compliance\RiskScoringEngine;
use App\Services\Contracts\CustomerServiceInterface;
use App\Services\CustomerScreeningService;
use App\Services\System\CacheInvalidationService;
use App\Services\System\CacheKeys;
use App\Services\System\EncryptionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Customer Service
 *
 * Handles all customer-related business logic including:
 * - Customer creation and updates
 * - Encryption of sensitive data
 * - Sanctions screening
 * - Risk assessment
 * - PEP and high-risk determination
 * - Blind index operations
 *
 * This service removes business logic from controllers and models,
 * ensuring proper MVC separation of concerns.
 */
class CustomerService implements CustomerServiceInterface
{
    public function __construct(
        protected EncryptionService $encryptionService,
        protected CustomerScreeningService $screeningService,
        protected RiskScoringEngine $riskScoringEngine,
        protected AuditService $auditService,
        protected AuditTrailHelper $auditTrailHelper,
        protected CacheInvalidationService $cacheInvalidationService,
        protected CustomerRepository $customerRepository
    ) {}

    /**
     * Create a customer and return the result with a success message.
     */
    public function createCustomerAction(array $data, int $createdBy): CustomerActionResult
    {
        $customer = $this->createCustomer($data, $createdBy);

        $message = "Customer {$customer->full_name} created successfully.";
        if ($customer->sanction_hit) {
            $message .= ' WARNING: Sanction match(es) found - customer flagged as High Risk.';
        }

        return new CustomerActionResult($customer, $message);
    }

    /**
     * Update a customer and return the result.
     */
    public function updateCustomerAction(Customer $customer, array $data, int $updatedBy): CustomerActionResult
    {
        $customer = $this->updateCustomer($customer, $data, $updatedBy);

        return new CustomerActionResult(
            $customer,
            "Customer {$customer->full_name} updated successfully."
        );
    }

    /**
     * Create a new customer with encryption, screening, and risk assessment.
     * Initial risk_rating is always 'Low' - automated risk scoring module determines actual risk.
     *
     * @param  array  $data  Customer data
     * @param  int  $userId  User ID creating the customer
     * @return Customer Created customer
     */
    public function createCustomer(array $data, int $userId): Customer
    {
        $customer = DB::transaction(function () use ($data, $userId) {
            // Duplicate-identity pre-check inside the creation transaction:
            // concurrent registrations with the same ID/phone would otherwise
            // split one person across two customer records and break AML
            // aggregation. Probed with withTrashed() because the unique index
            // added by migration also covers soft-deleted rows.
            $this->assertNoDuplicateBlindIndex($data);

            // Encrypt sensitive fields
            $encryptedData = $this->encryptCustomerData($data);

            // Create customer - risk_rating will be determined by screening and risk scoring
            // id_number_hash / phone_hash are NOT mass-assignable on the model,
            // so they must be assigned explicitly before save - otherwise the
            // blind indexes are never persisted and duplicate-identity checks
            // plus AML aggregation silently degrade.
            $customer = new Customer($encryptedData);
            foreach (['id_number_hash', 'phone_hash'] as $blindIndexField) {
                if (array_key_exists($blindIndexField, $encryptedData)) {
                    $customer->{$blindIndexField} = $encryptedData[$blindIndexField];
                }
            }
            $customer->save();

            // Screen against sanctions list FIRST - may set risk_rating to High if hit
            $this->screenCustomer($customer, $data['full_name']);

            // Calculate risk score using automated risk scoring engine (sets risk_rating)
            $this->calculateRiskScore($customer);

            // Log customer creation
            $user = User::find($userId);
            $this->auditTrailHelper->recordCustomer($customer->id, 'customer_created', [
                'new' => [
                    'full_name' => $customer->full_name,
                    'id_type' => $customer->id_type,
                    'nationality' => $customer->nationality,
                    'risk_rating' => $customer->risk_rating,
                    'pep_status' => $customer->pep_status,
                    'sanction_hit' => $customer->sanction_hit,
                ],
            ], $user, 'INFO', request()?->ip());

            return $customer;
        });
        $this->cacheInvalidationService->invalidate('dashboard');

        return $customer;
    }

    /**
     * Throw a validation error when another customer (including a soft-deleted
     * one) already holds the same ID number or phone blind index.
     *
     * @param  array<string, mixed>  $data  Raw customer input
     *
     * @throws ValidationException on duplicate identity
     */
    protected function assertNoDuplicateBlindIndex(array $data): void
    {
        if (! empty($data['id_number'])) {
            $idHash = self::computeBlindIndex($data['id_number']);

            if (Customer::withTrashed()->where('id_number_hash', $idHash)->exists()) {
                throw ValidationException::withMessages([
                    'id_number' => 'A customer with this ID number already exists.',
                ]);
            }
        }

        if (! empty($data['phone'])) {
            $phoneHash = self::computeBlindIndex($data['phone']);

            if (Customer::withTrashed()->where('phone_hash', $phoneHash)->exists()) {
                throw ValidationException::withMessages([
                    'phone' => 'A customer with this phone number already exists.',
                ]);
            }
        }
    }

    /**
     * Update an existing customer with encryption and risk reassessment.
     *
     * @param  Customer  $customer  Customer to update
     * @param  array  $data  Updated customer data
     * @param  int  $userId  User ID updating the customer
     * @return Customer Updated customer
     */
    public function updateCustomer(Customer $customer, array $data, int $userId): Customer
    {
        $customer = DB::transaction(function () use ($customer, $data, $userId) {
            // Encrypt sensitive fields if provided
            $encryptedData = $this->encryptCustomerData($data);

            // Capture original values before update for re-screening and audit logging
            $originalName = $customer->getOriginal('full_name');
            $originalRiskRating = $customer->getOriginal('risk_rating');

            // Update customer
            $customer->update($encryptedData);

            // Re-screen against sanctions if name changed
            if (isset($data['full_name']) && $data['full_name'] !== $originalName) {
                $this->screenCustomer($customer, $data['full_name']);
            }

            // Recalculate risk score (risk_rating is auto-determined by RiskScoringEngine)
            $this->calculateRiskScore($customer);

            // Log customer update
            $user = User::find($userId);
            $this->auditTrailHelper->recordCustomer($customer->id, 'customer_updated', [
                'old' => [
                    'full_name' => $originalName,
                    'risk_rating' => $originalRiskRating,
                ],
                'new' => [
                    'full_name' => $customer->full_name,
                    'risk_rating' => $customer->risk_rating,
                ],
            ], $user, 'INFO', request()?->ip());

            return $customer->fresh();
        });
        $this->cacheInvalidationService->invalidate('dashboard');
        // Invalidate individual customer cache
        $this->cacheInvalidationService->forgetCustomer($customer->id);

        return $customer;
    }

    /**
     * Get a customer by ID with caching.
     */
    public function getCustomer(int $customerId): ?Customer
    {
        return Cache::remember(
            CacheKeys::customer($customerId),
            now()->addMinutes(30),
            fn () => $this->customerRepository->findById($customerId)
        );
    }

    /**
     * Determine if a customer is a PEP associate.
     *
     * A customer is a PEP associate if they have any PEP relations.
     *
     * @param  Customer  $customer  Customer to check
     * @return bool True if customer is a PEP associate
     */
    public function isPepAssociate(Customer $customer): bool
    {
        return $customer->pepRelations()->where('is_pep', true)->exists();
    }

    /**
     * Determine if a customer is high risk.
     *
     * A customer is high risk if their risk rating is 'High',
     * they are a PEP, or they have a sanctions match.
     *
     * @param  Customer  $customer  Customer to check
     * @return bool True if customer is high risk
     */
    public function isHighRisk(Customer $customer): bool
    {
        return $customer->risk_rating === RiskRating::High
            || $customer->pep_status
            || $customer->sanction_hit;
    }

    /**
     * Compute a deterministic HMAC hash of the ID number for blind indexing.
     *
     * Blind indexing allows exact-match searches on encrypted fields
     * without decrypting the data.
     *
     * @param  string  $plaintext  Plaintext ID number
     * @return string HMAC-SHA256 hash
     */
    public static function computeBlindIndex(string $plaintext): string
    {
        return EncryptionService::blindIndex($plaintext);
    }

    /**
     * Find a customer by their ID number using the blind index.
     *
     * This allows searching for customers by ID number without
     * decrypting the encrypted field.
     *
     * @param  string  $idNumber  ID number to search for
     * @return Customer|null Customer if found, null otherwise
     */
    public function findByIdNumber(string $idNumber): ?Customer
    {
        return $this->customerRepository->findByIdNumber($idNumber);
    }

    public function searchCustomers(string $query, ?int $branchId = null): array
    {
        $query = trim($query);

        $customers = $this->customerRepository->searchActive($query, 10, $branchId);

        if ($customers->isEmpty()) {
            $idHash = $this->computeBlindIndex($query);
            $byHash = $this->customerRepository->findActiveByIdNumberHash($idHash, $branchId);
            if ($byHash) {
                $customers = collect([$byHash]);
            }
        }

        return $customers->map(function ($customer) {
            $sanctionCheck = $this->screeningService->screenName($customer->full_name);

            return [
                'id' => $customer->id,
                'full_name' => $customer->full_name,
                'ic_number' => $customer->ic_number,
                'ic_number_masked' => $customer->ic_number ? substr($customer->ic_number, 0, 4).'****'.substr($customer->ic_number, -4) : null,
                'nationality' => $customer->nationality,
                'risk_rating' => $customer->risk_rating,
                'cdd_level' => $customer->cdd_level instanceof CddLevel ? $customer->cdd_level->value : $customer->cdd_level,
                'is_pep' => $customer->pep_status,
                'is_sanctioned' => $customer->sanction_hit,
                'sanction_warning' => $sanctionCheck->matches->isNotEmpty(),
                'sanction_matches' => $sanctionCheck->matches->map(fn ($m) => [
                    'entity_name' => $m->entityName,
                    'score' => round($m->score, 1),
                    'list' => $m->listName,
                ])->toArray(),
                'sanction_action' => $sanctionCheck->action,
            ];
        })->toArray();
    }

    /**
     * Decrypt a customer's encrypted id_number.
     */
    public function decryptIdNumber(Customer $customer): ?string
    {
        if (empty($customer->id_number_encrypted)) {
            return null;
        }

        return $this->encryptionService->decrypt($customer->id_number_encrypted);
    }

    /**
     * Decrypt a customer's encrypted address.
     */
    public function decryptAddress(Customer $customer): string
    {
        if (empty($customer->address)) {
            return '';
        }

        return $this->encryptionService->decrypt($customer->address);
    }

    /**
     * Encrypt customer sensitive data.
     *
     * @param  array  $data  Customer data
     * @return array Encrypted customer data
     */
    protected function encryptCustomerData(array $data): array
    {
        $encrypted = $data;

        // Encrypt ID number and compute blind index
        if (isset($data['id_number'])) {
            $encrypted['id_number_encrypted'] = $this->encryptionService->encrypt($data['id_number']);
            $encrypted['id_number_hash'] = self::computeBlindIndex($data['id_number']);
            unset($encrypted['id_number']);
        }

        // Encrypt address
        if (! empty($data['address'])) {
            $encrypted['address'] = $this->encryptionService->encrypt($data['address']);
        }

        // Encrypt phone
        if (! empty($data['phone'])) {
            $encrypted['phone'] = $this->encryptionService->encrypt($data['phone']);
            $encrypted['phone_hash'] = self::computeBlindIndex($data['phone']);
        }

        // Encrypt employer address
        if (! empty($data['employer_address'])) {
            $encrypted['employer_address'] = $this->encryptionService->encrypt($data['employer_address']);
        }

        return $encrypted;
    }

    /**
     * Screen a customer against sanctions lists.
     *
     * @param  Customer  $customer  Customer to screen
     * @param  string  $fullName  Full name to screen
     */
    protected function screenCustomer(Customer $customer, string $fullName): void
    {
        $sanctionMatches = $this->screeningService->screenName($fullName);
        $hasSanctionHit = ! $sanctionMatches->isClear();

        // Update sanction status, risk rating, AND deactivate if hit found
        if ($hasSanctionHit) {
            $customer->risk_rating = 'High';
            $customer->sanction_hit = true;
            $customer->is_active = false; // Require Manager/Compliance approval to activate
            $customer->save();

            // Log sanction hit
            $this->auditService->logCustomerEvent(
                'customer_sanction_hit',
                $customer->id,
                [
                    'new_values' => [
                        'customer_name' => $customer->full_name,
                        'sanction_matches' => $sanctionMatches,
                    ],
                ],
                'WARNING'
            );
        }
    }

    /**
     * Soft-close a customer account.
     *
     * Guards against closing while transactions are still awaiting approval
     * or cancellation approval, then deactivates the profile and records the
     * closure reason and timestamp for the audit trail.
     *
     * @throws ValidationException when blocking transactions exist
     */
    public function closeCustomer(Customer $customer, string $reason, User $actor): Customer
    {
        $blocking = $customer->openBlockingTransactions();

        if ($blocking->isNotEmpty()) {
            throw ValidationException::withMessages([
                'closure' => 'Cannot close customer: '.$blocking->count().' transaction(s) pending approval or cancellation. Resolve them first.',
            ]);
        }

        return DB::transaction(function () use ($customer, $reason, $actor) {
            $customer->forceFill([
                'is_active' => false,
                'closure_reason' => $reason,
                'closed_at' => now(),
            ])->save();

            $this->auditService->logCustomerEvent('customer_closed', $customer->id, [
                'user_id' => $actor->id,
                'new_values' => [
                    'is_active' => false,
                    'closure_reason' => $reason,
                    'closed_at' => optional($customer->closed_at)->toIso8601String(),
                ],
            ], 'WARNING');

            $this->cacheInvalidationService->forgetCustomer($customer->id);

            return $customer;
        });
    }

    /**
     * Calculate risk score for a customer.
     *
     * @param  Customer  $customer  Customer to assess
     */
    protected function calculateRiskScore(Customer $customer): void
    {
        $this->riskScoringEngine->recalculateForCustomer($customer->id);
    }

    /**
     * Aggregate transaction statistics for a customer.
     *
     * @return array{total_transactions: int, total_volume: string, avg_transaction: float, last_transaction: string|null}
     */
    public function getTransactionStats(Customer $customer): array
    {
        $stats = $customer->transactions()
            ->selectRaw('COUNT(*) as total_transactions, SUM(amount_local) as total_volume, AVG(amount_local) as avg_transaction')
            ->first();

        return [
            'total_transactions' => (int) ($stats->total_transactions ?? 0),
            'total_volume' => $stats->total_volume ?? '0',
            'avg_transaction' => $stats->avg_transaction ?? '0',
            'last_transaction' => $customer->last_transaction_at?->toIso8601String(),
        ];
    }

    /**
     * Upload a KYC document for a customer.
     *
     * Persists the file to storage, creates the CustomerDocument record,
     * computes its SHA-256 hash for tamper-evidence, and logs the event
     * with audit severity so the KYC trail is preserved.
     *
     * @param  Customer  $customer  Customer to attach the document to
     * @param  UploadedFile  $file  Uploaded file
     * @param  string  $documentType  Document type enum value
     * @param  int  $uploadedBy  User ID uploading the document
     * @return CustomerDocument The persisted document record
     */
    public function uploadDocument(Customer $customer, UploadedFile $file, string $documentType, int $uploadedBy): CustomerDocument
    {
        $path = $file->store('kyc/'.$customer->id, 'local');

        $document = $customer->documents()->create([
            'document_type' => $documentType,
            'file_path' => $path,
            'file_hash' => hash_file('sha256', $file->getRealPath()),
            'file_size' => $file->getSize(),
            'uploaded_by' => $uploadedBy,
        ]);

        $this->auditService->logCustomerEvent(
            'kyc_document_uploaded',
            $customer->id,
            ['new_values' => ['document_type' => $documentType]]
        );

        return $document;
    }

    /**
     * Get customer show page data including document status and compliance stats.
     *
     * @return array{documentStatus: array, stats: array}
     */
    public function getCustomerShowData(Customer $customer): array
    {
        $documentStatus = [
            'total' => $customer->documents_count,
            'verified' => $customer->documents->filter->isVerified()->count(),
            'pending' => $customer->documents->whereNull('verified_by')->whereNull('verified_at')->count(),
            'expired' => $customer->documents->whereNotNull('expiry_date')->where('expiry_date', '<', now())->count(),
        ];

        $stats = [
            'total_transactions' => $customer->transactions_count,
            'total_value' => (float) ($customer->transactions_sum_amount_local ?? 0),
            'alerts' => Alert::where('customer_id', $customer->id)->count(),
            'str_filed' => StrReport::where('customer_id', $customer->id)
                ->where('status', '!=', StrReportStatus::Draft->value)
                ->count(),
        ];

        return [
            'documentStatus' => $documentStatus,
            'stats' => $stats,
        ];
    }
}
