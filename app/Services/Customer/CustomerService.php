<?php

namespace App\Services\Customer;

use App\Enums\CddLevel;
use App\Enums\RiskRating;
use App\Models\Customer;
use App\Models\User;
use App\Repositories\CustomerRepository;
use App\Services\Audit\AuditTrailHelper;
use App\Services\AuditService;
use App\Services\Compliance\RiskScoringEngine;
use App\Services\Contracts\CustomerServiceInterface;
use App\Services\CustomerScreeningService;
use App\Services\System\CacheTagsService;
use App\Services\System\EncryptionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
        protected CacheTagsService $cacheTagsService,
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
            // Encrypt sensitive fields
            $encryptedData = $this->encryptCustomerData($data);

            // Create customer
            $customer = Customer::create($encryptedData);

            // Initial risk always 'Low' - risk scoring module determines actual risk
            $customer->risk_rating = 'Low';
            $customer->save();

            // Screen against sanctions list (may upgrade to High if hit)
            $this->screenCustomer($customer, $data['full_name']);

            // Calculate risk score using automated risk scoring engine
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
        $this->cacheTagsService->invalidate('dashboard');

        return $customer;
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

            // Update customer
            $customer->update($encryptedData);

            // Risk rating is no longer mass-assignable; set it explicitly when provided.
            if (array_key_exists('risk_rating', $data) && filled($data['risk_rating'])) {
                $customer->risk_rating = $data['risk_rating'];
                $customer->save();
            }

            // Re-screen against sanctions if name changed
            if (isset($data['full_name']) && $data['full_name'] !== $customer->getOriginal('full_name')) {
                $this->screenCustomer($customer, $data['full_name']);
            }

            // Recalculate risk score
            $this->calculateRiskScore($customer);

            // Log customer update
            $user = User::find($userId);
            $this->auditTrailHelper->recordCustomer($customer->id, 'customer_updated', [
                'old' => [
                    'full_name' => $customer->getOriginal('full_name'),
                    'risk_rating' => $customer->getOriginal('risk_rating'),
                ],
                'new' => [
                    'full_name' => $customer->full_name,
                    'risk_rating' => $customer->risk_rating,
                ],
            ], $user, 'INFO', request()?->ip());

            return $customer->fresh();
        });
        $this->cacheTagsService->invalidate('dashboard');
        // Invalidate individual customer cache
        Cache::forget("customer:{$customer->id}");

        return $customer;
    }

    /**
     * Get a customer by ID with caching.
     */
    public function getCustomer(int $customerId): ?Customer
    {
        return Cache::remember(
            "customer:{$customerId}",
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
        $key = config('app.key');

        return hash_hmac('sha256', $plaintext, $key);
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

    public function searchCustomers(string $query): array
    {
        $query = trim($query);

        $customers = $this->customerRepository->searchActive($query);

        if ($customers->isEmpty()) {
            $idHash = $this->computeBlindIndex($query);
            $byHash = $this->customerRepository->findActiveByIdNumberHash($idHash);
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

        // Encrypt ID number
        if (isset($data['id_number'])) {
            $encrypted['id_number_encrypted'] = $this->encryptionService->encrypt($data['id_number']);
            unset($encrypted['id_number']);
        }

        // Encrypt address
        if (! empty($data['address'])) {
            $encrypted['address'] = $this->encryptionService->encrypt($data['address']);
        }

        // Encrypt phone
        if (! empty($data['phone'])) {
            $encrypted['phone'] = $this->encryptionService->encrypt($data['phone']);
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
            $this->auditService->logWithSeverity(
                'customer_sanction_hit',
                [
                    'entity_type' => 'Customer',
                    'entity_id' => $customer->id,
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
     * Calculate risk score for a customer.
     *
     * @param  Customer  $customer  Customer to assess
     */
    protected function calculateRiskScore(Customer $customer): void
    {
        $this->riskScoringEngine->recalculateForCustomer($customer->id);
    }
}
